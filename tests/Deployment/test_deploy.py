"""Synthetic contract tests: no SSH, credentials, application bootstrap or databases."""
import importlib.util
import contextlib
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

SPEC = importlib.util.spec_from_file_location(
    "draka_deploy", Path(__file__).resolve().parents[2] / "ops/deploy/deploy.py"
)
deploy = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(deploy)

SHA = "a" * 40


class ContractTest(unittest.TestCase):
    def test_forced_command_accepts_only_one_exact_sha(self):
        self.assertEqual(deploy.parse_command(f"deploy {SHA}"), SHA)
        for command in ["deploy", f"deploy {SHA}\n", f"deploy {SHA};id", "rollback " + SHA,
                        "deploy " + "A" * 40, "deploy " + "a" * 39, " deploy " + SHA]:
            with self.subTest(command=command), self.assertRaises(deploy.Refused):
                deploy.parse_command(command)

    def test_ci_rejects_wrong_sha_origin_attempt_and_non_green_results(self):
        run = {"id": 12, "workflow_id": 42, "path": ".github/workflows/ci.yml",
               "head_sha": SHA, "head_branch": "main", "event": "push",
               "head_repository": {"full_name": "owner/repo"},
               "status": "in_progress", "conclusion": None, "run_attempt": 2}
        jobs = [{"name": name, "status": "completed", "conclusion": "success",
                 "head_sha": SHA, "run_attempt": 2,
                 "steps": [{"name": step, "status": "completed", "conclusion": "success"}
                           for step in deploy.REQUIRED_STEPS[name]]} for name in deploy.REQUIRED_JOBS]
        deploy.validate_ci(run, jobs, SHA, "owner/repo", 42)
        # GitHub's attempt-specific jobs endpoint does not guarantee a run_attempt property per job.
        deploy.validate_ci(run, [{key: value for key, value in job.items() if key != "run_attempt"}
                                 for job in jobs], SHA, "owner/repo", 42)
        changed = [dict(job) for job in jobs]
        changed[0]["steps"] = [{**step, "conclusion": "skipped"} for step in jobs[0]["steps"]]
        with self.assertRaises(deploy.Refused):
            deploy.validate_ci(run, changed, SHA, "owner/repo", 42)
        for state in ["failure", "cancelled", "skipped", "neutral", "timed_out", None]:
            changed = [dict(job) for job in jobs]
            changed[0]["conclusion"] = state
            with self.subTest(state=state), self.assertRaises(deploy.Refused):
                deploy.validate_ci(run, changed, SHA, "owner/repo", 42)
        for key, value in [("head_sha", "b" * 40), ("event", "pull_request"),
                           ("head_branch", "feature/test"), ("conclusion", "cancelled"),
                           ("workflow_id", 43), ("path", ".github/workflows/other.yml")]:
            with self.subTest(key=key), self.assertRaises(deploy.Refused):
                deploy.validate_ci({**run, key: value}, jobs, SHA, "owner/repo", 42)
        for changed in [jobs[:-1], jobs + [jobs[0]], jobs + [{"name": "untested job"}],
                        [{**job, "run_attempt": 1} for job in jobs]]:
            with self.assertRaises(deploy.Refused):
                deploy.validate_ci(run, changed, SHA, "owner/repo", 42)

    def test_export_never_reads_database_legacy_secrets_or_generated_files(self):
        for name in ["public/mysql-8.4/ibdata1", "new/index.php", ".env", "public/.env",
                     "bootstrap/cache/config.php", "storage/logs/app.log", "vendor/autoload.php",
                     "node_modules/package/index.js", "../escape", "/absolute", "public/../secret"]:
            self.assertFalse(deploy.exportable(name), name)
        for name in ["artisan", "composer.lock", "app/Models/Player.php", "resources/js/app.js",
                     "public/assets/css/thehools.css", "database/migrations/2026_01_01_example.php"]:
            self.assertTrue(deploy.exportable(name), name)

    def test_migration_permit_is_exact_and_additive_only(self):
        migrations = {"database/migrations/2026_01_01_000001_example.php": "c" * 64}
        permit = {"sha": SHA, "base_sha": "b" * 40, "classification": "additive",
                  "rollback_compatible": True, "approved_by": "owner", "backup_reference": "backup-123",
                  "migrations": migrations}
        deploy.validate_permit(permit, SHA, "b" * 40, migrations)
        for changed in [{**permit, "sha": "d" * 40}, {**permit, "migrations": {}},
                        {**permit, "classification": "destructive"},
                        {**permit, "rollback_compatible": False}, {**permit, "backup_reference": ""}]:
            with self.assertRaises(deploy.Refused):
                deploy.validate_permit(changed, SHA, "b" * 40, migrations)

    def test_export_filters_before_blob_read_and_rejects_symlinks(self):
        with tempfile.TemporaryDirectory(prefix="draka-export-") as temporary:
            engine = object.__new__(deploy.Engine)
            reads = []
            tree = (b"100644 blob " + b"a" * 40 + b"\tpublic/mysql-8.4/ibdata1\0"
                    b"100644 blob " + b"b" * 40 + b"\tnew/legacy.php\0"
                    b"100644 blob " + b"c" * 40 + b"\t.env\0"
                    b"100644 blob " + b"d" * 40 + b"\tpublic/index.php\0")

            def git(*arguments):
                if arguments[0] == "ls-tree":
                    return tree
                reads.append(arguments)
                return b"synthetic application"

            engine.git = git
            engine.export(SHA, Path(temporary))
            self.assertEqual(reads, [("cat-file", "blob", "d" * 40)])
            self.assertEqual((Path(temporary) / "public/index.php").read_bytes(), b"synthetic application")
            tree = b"120000 blob " + b"e" * 40 + b"\tpublic/escape\0"
            with self.assertRaises(deploy.Refused):
                engine.export(SHA, Path(temporary))

    def test_modified_or_deleted_migrations_never_enter_auto_mode(self):
        engine = object.__new__(deploy.Engine)
        engine.config = {"allow_additive_migrations": True}
        original = {"database/migrations/2026_01_01_000001_example.php": "c" * 64}
        for new in [{}, {next(iter(original)): "d" * 64}]:
            engine.migrations = lambda sha: original if sha == SHA else new
            with self.assertRaises(deploy.Refused):
                engine.migration_plan("b" * 40, SHA)
        engine.config["allow_additive_migrations"] = False
        engine.migrations = lambda sha: {} if sha == SHA else original
        with self.assertRaises(deploy.Refused):
            engine.migration_plan("b" * 40, SHA)


class SyntheticEngine(deploy.Engine):
    """Real transaction engine and state files; every external side effect is replaced."""
    def __init__(self, root):
        super().__init__({"root": str(root), "repository": "owner/repo", "workflow_id": 42,
                          "git_ssh_config": "/unused", "github_config": "/unused"})
        self.events = []
        self.calls = []
        self.ci_count = 0
        self.fail_ci = 0
        self.fail_prepare = False
        self.fail_health = set()
        self.fail_activated_audit = False
        self.fail_migration = False
        self.added_migrations = {}
        self.pointer = SHA + "-baseline"
        self.state_dir.mkdir()
        self.releases.mkdir()
        (self.releases / self.pointer).mkdir()
        self.state_file.write_text(json.dumps({"active_sha": SHA, "high_water_sha": SHA,
                                               "release": self.pointer}), encoding="utf-8")

    def git(self, *arguments):
        self.calls.append(arguments)
        if arguments[:2] == ("remote", "get-url"):
            return b"git@github.com:owner/repo.git\n"
        if arguments[:2] == ("merge-base", "--is-ancestor"):
            old, new = arguments[2:]
            if new != "refs/remotes/origin/main" and old > new:
                raise deploy.Refused("not a descendant")
        return b""

    def provisioned_layout(self):
        # Fixtures deliberately have no real .env, mirror, shared storage or credentials.
        pass

    def verify_ci(self, sha):
        self.ci_count += 1
        if self.ci_count == self.fail_ci:
            raise deploy.Refused("synthetic CI refusal")

    def migration_plan(self, sha, baseline):
        return self.added_migrations

    def prepare(self, sha):
        if self.fail_prepare:
            raise deploy.Refused("synthetic build failure")
        release = self.releases / (sha + "-candidate")
        release.mkdir()
        return release

    def command(self, arguments, label, **kwargs):
        self.calls.append(arguments)
        if self.fail_migration:
            raise deploy.Refused("synthetic migration failure")

    def health(self, sha):
        if sha in self.fail_health:
            raise deploy.Refused("synthetic unhealthy release")

    def switch(self, release):
        self.pointer = release

    def load_state(self):
        state = deploy.read_json(self.state_file)
        if state["release"] != self.pointer:
            raise deploy.Refused("synthetic state mismatch")
        return state

    def audit(self, event, **fields):
        self.events.append((event, fields))
        if event == "activated" and self.fail_activated_audit:
            raise OSError("synthetic audit disk failure")


class TransactionTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="draka-transaction-")
        self.root = Path(self.temporary.name).resolve()
        self.engine = SyntheticEngine(self.root)
        self.lock = patch.object(deploy, "deployment_lock", lambda *_: contextlib.nullcontext())
        self.sync = patch.object(deploy, "sync_directory")
        self.lock.start()
        self.sync.start()

    def tearDown(self):
        self.sync.stop()
        self.lock.stop()
        self.temporary.cleanup()

    def test_success_exact_sha_idempotency_and_old_run_refusal(self):
        target = "b" * 40
        self.engine.deploy(target)
        state = deploy.read_json(self.engine.state_file)
        self.assertEqual(state["active_sha"], target)
        self.assertEqual(state["high_water_sha"], target)
        self.assertEqual(self.engine.pointer, target + "-candidate")
        self.assertFalse(self.engine.journal.exists())
        self.engine.deploy(target)  # A lost SSH receipt does not redeploy or rerun migrations.
        with self.assertRaises(deploy.Refused):
            self.engine.deploy(SHA)
        self.assertEqual(deploy.read_json(self.engine.state_file), state)

    def test_build_or_second_ci_failure_never_switches(self):
        for failure in ["build", "ci"]:
            self.engine.fail_prepare = failure == "build"
            self.engine.fail_ci = 2 if failure == "ci" else 0
            self.engine.ci_count = 0
            with self.assertRaises(deploy.Refused):
                self.engine.deploy("b" * 40)
            self.assertEqual(self.engine.pointer, SHA + "-baseline")
            self.assertFalse(self.engine.journal.exists())

    def test_health_failure_restores_pointer_and_blocks_unattended_retries(self):
        self.engine.fail_health.add("b" * 40)
        with self.assertRaises(deploy.Refused):
            self.engine.deploy("b" * 40)
        self.assertEqual(self.engine.pointer, SHA + "-baseline")
        self.assertEqual(deploy.read_json(self.engine.state_file)["high_water_sha"], SHA)
        self.assertTrue(self.engine.journal.exists())
        with self.assertRaises(deploy.Refused):
            self.engine.deploy("c" * 40)

    def test_failure_after_state_commit_never_rewinds_high_water_mark(self):
        self.engine.fail_activated_audit = True
        with self.assertRaises(OSError):
            self.engine.deploy("b" * 40)
        state = deploy.read_json(self.engine.state_file)
        self.assertEqual(state["active_sha"], SHA)
        self.assertEqual(state["high_water_sha"], "b" * 40)
        self.assertTrue(self.engine.journal.exists())

    def test_failed_migration_keeps_old_code_and_requires_operator(self):
        self.engine.added_migrations = {"database/migrations/2026_01_01_000001_example.php": "c" * 64}
        self.engine.fail_migration = True
        with self.assertRaises(deploy.Refused):
            self.engine.deploy("b" * 40)
        self.assertEqual(self.engine.pointer, SHA + "-baseline")
        self.assertTrue(self.engine.journal.exists())
        self.assertFalse(any("rollback" in argument for call in self.engine.calls for argument in call))


if __name__ == "__main__":
    unittest.main()
