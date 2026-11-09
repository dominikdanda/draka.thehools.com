"""Linux integration checks, executed explicitly in CI. Never contacts a service."""
import multiprocessing
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

from test_deploy import deploy, SyntheticEngine, SHA


def try_lock(path, connection):
    try:
        with deploy.deployment_lock(path, wait_seconds=0):
            connection.send("acquired")
    except deploy.Refused:
        connection.send("blocked")
    finally:
        connection.close()


class PosixEngine(SyntheticEngine):
    switch = deploy.Engine.switch
    load_state = deploy.Engine.load_state


class PosixContractTest(unittest.TestCase):
    def test_real_kernel_lock_excludes_another_process(self):
        with tempfile.TemporaryDirectory(prefix="draka-lock-") as temporary:
            path = Path(temporary) / "lock"
            for locked, expected in [(True, "blocked"), (False, "acquired")]:
                parent, child = multiprocessing.Pipe()
                # spawn ensures the child does not inherit the parent's locked descriptor.
                process = multiprocessing.get_context("spawn").Process(target=try_lock, args=(path, child))
                if locked:
                    with deploy.deployment_lock(path):
                        process.start()
                        self.assertTrue(parent.poll(10))
                        self.assertEqual(parent.recv(), expected)
                        process.join(10)
                else:
                    process.start()
                    self.assertTrue(parent.poll(10))
                    self.assertEqual(parent.recv(), expected)
                    process.join(10)
                self.assertEqual(process.exitcode, 0)
                parent.close()
                child.close()

    def test_real_atomic_symlink_state_and_failed_health_recovery(self):
        with tempfile.TemporaryDirectory(prefix="draka-posix-") as temporary:
            engine = PosixEngine(Path(temporary).resolve())
            engine.current.symlink_to("releases/" + SHA + "-baseline")
            engine.deploy("b" * 40)
            state = engine.load_state()
            self.assertEqual(state["active_sha"], "b" * 40)
            self.assertEqual(engine.current.resolve().name, "b" * 40 + "-candidate")
            engine.fail_health.add("c" * 40)
            with self.assertRaises(deploy.Refused):
                engine.deploy("c" * 40)
            self.assertEqual(engine.current.resolve().name, "b" * 40 + "-candidate")
            self.assertEqual(engine.load_state(), state)
            self.assertTrue(engine.journal.exists())

    def test_command_output_is_never_forwarded_and_timeout_kills_process(self):
        with tempfile.TemporaryDirectory(prefix="draka-process-") as temporary:
            engine = SyntheticEngine(Path(temporary).resolve())
            with self.assertRaises(deploy.Refused) as error:
                deploy.Engine.command(engine, [sys.executable, "-c", "print('SYNTHETIC_SECRET');exit(1)"], "fixture")
            self.assertNotIn("SYNTHETIC_SECRET", str(error.exception))
            with self.assertRaises(subprocess.TimeoutExpired):
                deploy.Engine.command(engine, [sys.executable, "-c", "import time; time.sleep(30)"],
                                      "fixture timeout", timeout=0.1)

    def test_control_file_must_not_be_replaceable_by_deploy_user(self):
        with tempfile.TemporaryDirectory(prefix="draka-control-") as temporary:
            path = Path(temporary) / "config.json"
            path.write_text("{}")
            path.chmod(0o666)
            with self.assertRaises(deploy.Refused):
                deploy.secure_file(path)


if __name__ == "__main__":
    if sys.platform != "linux":
        raise SystemExit("POSIX contract checks require a disposable Linux runner")
    unittest.main()
