"""Candidate Linux VPS deploy engine. Never import application code into this process.

Production entry point has fixed configuration; tests use synthetic repositories and fakes.
No interactive shell, rollback, arbitrary path, command or environment input is accepted by SSH.
"""
import contextlib
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import signal
import stat
import subprocess
import sys
import time
from urllib.parse import urlencode, urlsplit
import uuid

REQUIRED_JOBS = {"PHP tests", "JavaScript tests and build", "CI gate"}
REQUIRED_STEPS = {
    "PHP tests": {"Prepare Laravel runtime directories", "Reject local environment and cached configuration",
                  "Run composer test -- --fail-on-skipped --fail-on-incomplete --fail-on-risky",
                  "PHP syntax", "Infrastructure formatting"},
    "JavaScript tests and build": {"Run npm test", "Run npm run build",
                                  "VPS deployment contract (synthetic fixtures only)"},
    "CI gate": {"Require every suite to succeed"},
}
SHA_RE = re.compile(r"[0-9a-f]{40}")
MIGRATION_RE = re.compile(r"database/migrations/[0-9_]+[a-z0-9_]+\.php")


class Refused(Exception):
    pass


def parse_command(command):
    match = re.fullmatch(r"deploy ([0-9a-f]{40})", command)
    if not match:
        raise Refused("invalid forced command")
    return match[1]


def exportable(name):
    path = PurePosixPath(name)
    if (not name or str(path) != name or path.is_absolute() or "\\" in name
            or any(part in {"..", ".git"} or part.startswith(".env") for part in path.parts)):
        return False
    if name == "public/hot" or name.startswith(("public/mysql-8.4/", "public/build/", "public/storage/", "bootstrap/cache/")):
        return False
    return (path.parts[0] in {"app", "bootstrap", "config", "database", "lang", "public", "resources", "routes"}
            or name in {"artisan", "composer.json", "composer.lock", "package.json", "package-lock.json",
                        "vite.config.js", "vite.config.ts"})


def validate_ci(run, jobs, sha, repository, workflow_id):
    if (run.get("head_sha") != sha or run.get("head_branch") != "main"
            or run.get("event") != "push" or run.get("workflow_id") != workflow_id
            or run.get("path", "").split("@")[0] != ".github/workflows/ci.yml"
            or run.get("head_repository", {}).get("full_name") != repository
            or not isinstance(run.get("run_attempt"), int) or run["run_attempt"] < 1
            or (run.get("status"), run.get("conclusion")) not in {
                ("in_progress", None), ("completed", "success")}):
        raise Refused("CI provenance or conclusion rejected")
    tests = [job for job in jobs if not (job.get("name") == "deploy"
                                       or job.get("name", "").startswith("deploy / "))]
    if len(tests) != len(REQUIRED_JOBS) or {job.get("name") for job in tests} != REQUIRED_JOBS:
        raise Refused("CI test job set is incomplete or unexpected")
    if any(job.get("status") != "completed" or job.get("conclusion") != "success"
           or job.get("head_sha") != sha
           or ("run_attempt" in job and job["run_attempt"] != run["run_attempt"])
           for job in tests):
        raise Refused("CI tests are not all green for this attempt and SHA")
    for job in tests:
        steps = job.get("steps", [])
        for required in REQUIRED_STEPS[job["name"]]:
            matching = [step for step in steps if step.get("name") == required]
            if (len(matching) != 1 or matching[0].get("status") != "completed"
                    or matching[0].get("conclusion") != "success"):
                raise Refused("required CI test step is missing, skipped or unsuccessful")


def validate_permit(permit, sha, baseline, migrations):
    if (permit.get("sha") != sha or permit.get("base_sha") != baseline
            or permit.get("classification") != "additive"
            or permit.get("rollback_compatible") is not True
            or permit.get("migrations") != migrations
            or not isinstance(permit.get("approved_by"), str) or not permit["approved_by"].strip()
            or not isinstance(permit.get("backup_reference"), str) or not permit["backup_reference"].strip()):
        raise Refused("migration approval does not match exact reviewed content")


def secure_file(path):
    """Root-owned control files and ancestors, never writable by deploy/web users."""
    path = Path(path)
    if not path.is_absolute():
        raise Refused("control path must be absolute")
    for item in [path, *path.parents]:
        info = item.lstat()
        if stat.S_ISLNK(info.st_mode) or info.st_uid != 0 or info.st_mode & 0o022:
            raise Refused("unsafe control file ownership or permissions")
    if not path.is_file():
        raise Refused("missing control file")


def read_json(path):
    with Path(path).open(encoding="utf-8") as stream:
        return json.load(stream)


def sync_directory(path):
    descriptor = os.open(path, os.O_RDONLY | os.O_DIRECTORY)
    try:
        os.fsync(descriptor)
    finally:
        os.close(descriptor)


def atomic_json(path, content):
    path = Path(path)
    temporary = path.with_name(path.name + "." + uuid.uuid4().hex)
    descriptor = os.open(temporary, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(descriptor, "w", encoding="utf-8") as stream:
        json.dump(content, stream, sort_keys=True)
        stream.flush()
        os.fsync(stream.fileno())
    os.replace(temporary, path)
    sync_directory(path.parent)


@contextlib.contextmanager
def deployment_lock(path, wait_seconds=120):
    import fcntl  # Linux-only runtime; pure policy tests also run on Windows.
    descriptor = os.open(path, os.O_RDWR | os.O_CREAT | os.O_NOFOLLOW, 0o600)
    deadline = time.monotonic() + wait_seconds
    try:
        while True:
            try:
                fcntl.flock(descriptor, fcntl.LOCK_EX | fcntl.LOCK_NB)
                break
            except BlockingIOError:
                if time.monotonic() >= deadline:
                    raise Refused("deployment lock timeout") from None
                time.sleep(0.2)
        yield
    finally:
        os.close(descriptor)


class Engine:
    def __init__(self, config):
        self.config = config
        self.root = Path(config["root"])
        if (not self.root.is_absolute() or len(self.root.parts) < 3
                or self.root.resolve() != self.root):
            raise Refused("invalid deployment root")
        self.state_dir = self.root / "state"
        self.releases = self.root / "releases"
        self.mirror = self.root / "repo.git"
        self.state_file = self.state_dir / "state.json"
        self.journal = self.state_dir / "transaction.json"
        self.current = self.root / "current"
        self.env = {"PATH": "/usr/local/bin:/usr/bin:/bin", "HOME": "/home/draka-deploy",
                    "LANG": "C.UTF-8", "GIT_TERMINAL_PROMPT": "0",
                    "GIT_SSH_COMMAND": "/usr/bin/ssh -F " + config["git_ssh_config"],
                    "GH_CONFIG_DIR": config["github_config"], "GH_PROMPT_DISABLED": "1"}
        self.switched = False
        self.previous = None
        self.stage = "preflight"

    def command(self, arguments, label, cwd=None, capture=False, timeout=300):
        # Only fixed argument arrays, no shell. Output is never copied to SSH/audit logs.
        self.stage = label
        environment = self.env
        if cwd is not None:
            # Do not propagate GitHub/SSH credential configuration to build or Artisan processes.
            environment = {"PATH": self.env["PATH"], "HOME": str(cwd), "LANG": "C.UTF-8", "CI": "true",
                           "COMPOSER_HOME": str(cwd / ".composer-home"),
                           "COMPOSER_CACHE_DIR": str(self.state_dir / "cache/composer"),
                           "npm_config_cache": str(self.state_dir / "cache/npm")}
        process = subprocess.Popen(arguments, cwd=cwd, env=environment, stdin=subprocess.DEVNULL,
                                   stdout=subprocess.PIPE if capture else subprocess.DEVNULL,
                                   stderr=subprocess.DEVNULL, start_new_session=True)
        try:
            output, _ = process.communicate(timeout=timeout)
        except BaseException:
            with contextlib.suppress(ProcessLookupError):
                os.killpg(process.pid, signal.SIGKILL)
            process.wait()
            raise
        if process.returncode:
            raise Refused(label + " failed")
        return output if capture else None

    def git(self, *arguments):
        return self.command(["git", "-c", "core.hooksPath=/dev/null", "--git-dir=" + str(self.mirror),
                             *arguments], "git", capture=True)

    def api(self, endpoint):
        return json.loads(self.command(["gh", "api", "--hostname", "github.com", "-H",
                                       "X-GitHub-Api-Version: 2022-11-28", endpoint],
                                      "GitHub verification", capture=True, timeout=30))

    def audit(self, event, **fields):
        record = {"time": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()), "event": event, **fields}
        descriptor = os.open(self.state_dir / "audit.jsonl",
                             os.O_WRONLY | os.O_APPEND | os.O_CREAT | os.O_NOFOLLOW, 0o600)
        with os.fdopen(descriptor, "a", encoding="utf-8") as stream:
            stream.write(json.dumps(record, sort_keys=True) + "\n")
            stream.flush()
            os.fsync(stream.fileno())
        print("draka-deploy: " + event, file=sys.stderr, flush=True)

    def verify_ci(self, sha):
        repo = self.config["repository"]
        query = urlencode({"head_sha": sha, "branch": "main", "event": "push", "per_page": 100})
        listing = self.api(f"repos/{repo}/actions/workflows/{self.config['workflow_id']}/runs?{query}")
        if not listing.get("workflow_runs") or listing.get("total_count", 0) > 100:
            raise Refused("no unambiguous push CI for SHA")
        run = max(listing["workflow_runs"], key=lambda item: item["id"])
        run = self.api(f"repos/{repo}/actions/runs/{run['id']}")
        jobs = self.api(f"repos/{repo}/actions/runs/{run['id']}/attempts/{run['run_attempt']}/jobs?per_page=100")
        if jobs.get("total_count", 0) > 100:
            raise Refused("unexpected CI job count")
        # The attempt-specific URL binds jobs to this attempt even when job JSON omits run_attempt.
        refreshed = self.api(f"repos/{repo}/actions/runs/{run['id']}")
        if refreshed["run_attempt"] != run["run_attempt"]:
            raise Refused("CI attempt changed during verification")
        validate_ci(refreshed, jobs["jobs"], sha, repo, self.config["workflow_id"])
        self.audit("ci-verified", sha=sha, run=run["id"], attempt=run["run_attempt"])

    def load_state(self):
        state = read_json(self.state_file)
        if (not all(isinstance(state.get(key), str) and SHA_RE.fullmatch(state[key])
                    for key in ["active_sha", "high_water_sha"])
                or not re.fullmatch(r"[0-9a-f]{40}-[a-zA-Z0-9]+", state.get("release", ""))):
            raise Refused("invalid deployment state; operator required")
        expected = self.releases / state["release"]
        if not self.current.is_symlink() or self.current.resolve() != expected or not expected.is_dir():
            raise Refused("current symlink and state disagree; operator required")
        return state

    def migrations(self, sha):
        result = {}
        for record in self.git("ls-tree", "-r", "-z", sha, "--", "database/migrations").split(b"\0"):
            if not record:
                continue
            metadata, name = record.split(b"\t", 1)
            path = name.decode("utf-8")
            if not path.endswith(".php"):
                continue
            mode, kind, oid = metadata.decode("ascii").split()
            if kind != "blob" or mode not in {"100644", "100755"} or not MIGRATION_RE.fullmatch(path):
                raise Refused("unsupported migration path or type")
            result[path] = hashlib.sha256(self.git("cat-file", "blob", oid)).hexdigest()
        return result

    def migration_plan(self, sha, baseline):
        old, new = self.migrations(baseline), self.migrations(sha)
        if any(new.get(path) != digest for path, digest in old.items()):
            raise Refused("edited/deleted migration requires separate manual data operation")
        added = {path: digest for path, digest in new.items() if path not in old}
        if added:
            if not self.config.get("allow_additive_migrations", False):
                raise Refused("automatic migrations are disabled")
            permit_path = Path("/etc/draka-deploy/migration-permits") / (sha + ".json")
            secure_file(permit_path)
            validate_permit(read_json(permit_path), sha, baseline, added)
        return added

    def export(self, sha, release):
        for record in self.git("ls-tree", "-r", "-z", sha).split(b"\0"):
            if not record:
                continue
            metadata, raw_name = record.split(b"\t", 1)
            name = raw_name.decode("utf-8")
            # Filter BEFORE reading blobs: never materialize the tracked live DB or legacy tree.
            if not exportable(name):
                continue
            mode, kind, oid = metadata.decode("ascii").split()
            if kind != "blob" or mode not in {"100644", "100755"}:
                raise Refused("symlinks and submodules in release source are forbidden")
            path = release / name
            path.parent.mkdir(parents=True, exist_ok=True, mode=0o750)
            with path.open("xb") as stream:
                stream.write(self.git("cat-file", "blob", oid))
            path.chmod(0o750 if mode == "100755" else 0o640)

    def prepare(self, sha):
        release = self.releases / (sha + "-" + uuid.uuid4().hex)
        release.mkdir(mode=0o750)
        self.export(sha, release)
        self.command(["composer", "install", "--no-dev", "--prefer-dist", "--no-interaction",
                      "--no-progress", "--no-scripts", "--no-plugins", "--optimize-autoloader"],
                     "composer install", cwd=release)
        self.command(["composer", "--no-plugins", "check-platform-reqs", "--no-dev"],
                     "platform check", cwd=release)
        self.command(["npm", "ci", "--ignore-scripts", "--no-audit", "--no-fund"], "npm ci", cwd=release)
        self.command(["npm", "run", "build"], "asset build", cwd=release)
        if not (release / "public/build/manifest.json").is_file():
            raise Refused("asset manifest missing")
        shared = self.root / "shared"
        for relative in ["app", "logs", "framework/sessions", "framework/cache"]:
            destination = shared / "storage" / relative
            if not destination.is_dir():
                raise Refused("shared runtime directories must be provisioned by operator")
            link = release / "storage" / relative
            link.parent.mkdir(parents=True, exist_ok=True, mode=0o750)
            link.symlink_to(destination, target_is_directory=True)
        if not (shared / ".env").is_file():
            raise Refused("environment file must be provisioned by operator")
        (release / ".env").symlink_to(shared / ".env")
        for relative in ["bootstrap/cache", "storage/framework/views", "storage/framework/testing"]:
            path = release / relative
            path.mkdir(parents=True, exist_ok=True, mode=0o770)
            path.chmod(0o770)
        if not (shared / "storage/app/public").is_dir():
            raise Refused("shared public uploads must be provisioned by operator")
        (release / "public/storage").symlink_to(shared / "storage/app/public", target_is_directory=True)
        for operation in ["package:discover", "config:cache", "route:cache", "view:cache"]:
            self.command(["php", "artisan", operation, "--no-interaction"], operation, cwd=release)
        self.write_probe(release, sha)
        return release

    def write_probe(self, release, sha):
        # Unique PHP URI forces the health check through FPM and this release's Laravel kernel.
        # No debug response or exception content is exposed; no game route or DB query is invoked.
        probe = release / "public/.well-known" / ("draka-release-" + sha + ".php")
        probe.parent.mkdir(mode=0o750, exist_ok=True)
        probe.write_text("""<?php
ob_start();
try {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\\Contracts\\Http\\Kernel::class);
    $request = Illuminate\\Http\\Request::create('/up');
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    $healthy = $response->getStatusCode() === 200;
} catch (Throwable $exception) {
    $healthy = false;
}
while (ob_get_level() > 0) { ob_end_clean(); }
header('Cache-Control: no-store');
header('Content-Type: text/plain');
http_response_code($healthy ? 200 : 503);
echo $healthy ? 'healthy """ + sha + "' : 'unhealthy';\n", encoding="utf-8")
        probe.chmod(0o640)

    def health(self, sha):
        origin = self.config["health_origin"]
        parsed = urlsplit(origin)
        if (parsed.scheme != "https" or parsed.port not in {None, 443} or not parsed.hostname
                or parsed.username or parsed.password or parsed.path or parsed.query or parsed.fragment):
            raise Refused("health origin must be a bare HTTPS origin")
        for attempt in range(5):
            try:
                response = self.command(["curl", "--fail", "--silent", "--proto", "=https",
                                         "--noproxy", "*", "--max-time", "10", "--resolve",
                                         parsed.hostname + ":443:127.0.0.1", origin + "/.well-known/"
                                         + "draka-release-" + sha + ".php"],
                                        "release health", capture=True, timeout=15)
                if response == ("healthy " + sha).encode("ascii"):
                    return
            except Refused:
                pass
            if attempt < 4:
                time.sleep(2)
        raise Refused("release health or identity check failed")

    def switch(self, release):
        temporary = self.root / (".current-" + uuid.uuid4().hex)
        temporary.symlink_to("releases/" + release, target_is_directory=True)
        os.replace(temporary, self.current)
        sync_directory(self.root)

    def provisioned_layout(self):
        for path in [self.root, self.state_dir, self.releases, self.mirror, self.root / "shared"]:
            if not path.is_dir() or path.is_symlink() or path.stat().st_mode & 0o022:
                raise Refused("deployment layout must be provisioned without writable/symlink control directories")
        for path in [self.state_dir, self.mirror]:
            if path.stat().st_uid != os.geteuid() or path.stat().st_mode & 0o077:
                raise Refused("private state/mirror permissions required")
        if (self.state_file.is_symlink() or not self.state_file.is_file()
                or self.state_file.stat().st_uid != os.geteuid() or self.state_file.stat().st_mode & 0o077):
            raise Refused("private initial state must be supplied by operator")
        environment = self.root / "shared/.env"
        if (environment.is_symlink() or not environment.is_file()
                or environment.stat().st_uid != 0 or environment.stat().st_mode & 0o022):
            raise Refused("shared environment must be a root-managed non-writable regular file")

    def deploy(self, sha):
        # Existing state, shared directories and partial bare mirror are provisioned explicitly.
        self.provisioned_layout()
        with deployment_lock(self.state_dir / "deploy.lock"):
            if self.journal.exists():
                raise Refused("unfinished transaction; operator recovery required")
            self.previous = state = self.load_state()
            self.audit("started", sha=sha)
            expected_origin = "git@github.com:" + self.config["repository"] + ".git"
            if self.git("remote", "get-url", "origin").decode().strip() != expected_origin:
                raise Refused("unexpected repository origin")
            self.git("fetch", "--no-tags", "--filter=blob:none", "origin",
                     "+refs/heads/main:refs/remotes/origin/main")
            self.git("merge-base", "--is-ancestor", sha, "refs/remotes/origin/main")
            self.git("merge-base", "--is-ancestor", state["high_water_sha"], sha)
            self.verify_ci(sha)
            if sha == state["high_water_sha"]:
                if sha != state["active_sha"]:
                    raise Refused("operator rollback is active; require a newer reviewed commit")
                self.health(sha)
                self.audit("already-deployed", sha=sha)
                return
            migrations = self.migration_plan(sha, state["high_water_sha"])
            release = self.prepare(sha)
            # Recheck latest attempt after build, before any DDL or activation.
            self.verify_ci(sha)
            atomic_json(self.journal, {"sha": sha, "candidate": release.name, "previous": state})
            try:
                for path in sorted(migrations):
                    self.audit("approved-additive-migration", sha=sha, path=path)
                    self.command(["php", "artisan", "migrate", "--force", "--no-interaction",
                                  "--path=" + path], "approved migration", cwd=release, timeout=120)
                # No application/database cache flush or migration rollback is ever run.
                self.switched = True
                self.switch(release.name)
                self.health(sha)
                atomic_json(self.state_file, {"active_sha": sha, "high_water_sha": sha, "release": release.name})
                self.audit("activated", sha=sha)
                self.journal.unlink()
                sync_directory(self.state_dir)
                self.switched = False
            except BaseException:
                self.recover()
                raise

    def recover(self):
        # Never undo schema changes. A journal survives failure and blocks unattended retries.
        if not self.journal.exists():
            atomic_json(self.journal, {"recovery_required": True, "previous": self.previous})
        if self.switched:
            self.switch(self.previous["release"])
            self.health(self.previous["active_sha"])
            current_state = read_json(self.state_file)
            atomic_json(self.state_file, {**self.previous, "high_water_sha": current_state["high_water_sha"]})
            self.audit("previous-release-restored", sha=self.previous["active_sha"])
        self.audit("operator-recovery-required")


def main():
    engine = None
    try:
        sha = parse_command(os.environ.get("SSH_ORIGINAL_COMMAND", ""))
        if sys.platform != "linux" or len(sys.argv) != 1:
            raise Refused("Linux forced-command entry only")
        import pwd
        if pwd.getpwuid(os.geteuid()).pw_name != "draka-deploy":
            raise Refused("requires unprivileged draka-deploy account")
        secure_file(__file__)
        secure_file("/home/draka-deploy/deploy-draka.sh")
        config_path = "/etc/draka-deploy/config.json"
        secure_file(config_path)
        config = read_json(config_path)
        if config.get("enabled") is not True:
            raise Refused("deployment mechanism is disabled")
        if (not re.fullmatch(r"[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+", config["repository"])
                or not isinstance(config["workflow_id"], int) or config["workflow_id"] <= 0
                or not re.fullmatch(r"/[A-Za-z0-9_./-]+", config["git_ssh_config"])):
            raise Refused("invalid fixed configuration")
        secure_file(config["git_ssh_config"])
        # gh handles credentials internally; this process never reads or prints token files.
        secure_file(Path(config["github_config"]) / "hosts.yml")
        os.umask(0o027)
        engine = Engine(config)
        def interrupted(*_):
            raise Refused("interrupted")
        for signum in [signal.SIGTERM, signal.SIGHUP, signal.SIGINT, signal.SIGALRM]:
            signal.signal(signum, interrupted)
        signal.alarm(720)  # Server-side lease ends before the 15-minute Actions job timeout.
        engine.deploy(sha)
        print("deployed " + sha, flush=True)
        return 0
    except (Exception, KeyboardInterrupt):
        # Never forward command output, URLs with credentials, environment or tracebacks to SSH.
        if engine:
            try:
                engine.audit("failed", stage=engine.stage)
            except Exception:
                pass
        print("draka-deploy: refused or failed; inspect private state and recovery journal", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
