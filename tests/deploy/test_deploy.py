import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest


class DeployTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        (self.root / "bin").mkdir()
        shutil.copyfile(Path(__file__).resolve().parents[2] / "bin/deploy", self.root / "bin/deploy")
        (self.root / ".env.production").write_text("test-only-config\n")
        docker = self.root / "bin/docker"
        docker.write_text(
            "#!" + sys.executable + "\n"
            "import json, os, sys\n"
            "from pathlib import Path\n"
            "args=sys.argv[1:]\n"
            "with open(os.environ['DEPLOY_TEST_LOG'],'a') as f: f.write(json.dumps(args)+'\\n')\n"
            "command=' '.join(args)\n"
            "stage=os.environ.get('DEPLOY_TEST_FAIL','')\n"
            "if stage=='build' and 'build php nginx' in command: sys.exit(11)\n"
            "if stage=='backup' and 'pg_dump' in command: sys.exit(12)\n"
            "if stage=='migration' and 'doctrine:migrations:migrate' in command: sys.exit(13)\n"
            "if 'pg_dump' in command: print('fake-dump')\n"
            "if 'tar -C' in command: print('fake-avatar-archive')\n"
        )
        docker.chmod(0o755)
        self.env = dict(os.environ, PATH=str(self.root / "bin") + os.pathsep + os.environ["PATH"],
                        DEPLOY_TEST_LOG=str(self.root / "commands.jsonl"))

    def deploy(self, *args, fail=""):
        return subprocess.run(["sh", str(self.root / "bin/deploy"), *args],
                              cwd=self.root, env=dict(self.env, DEPLOY_TEST_FAIL=fail),
                              capture_output=True, text=True)

    def commands(self):
        return [json.loads(line) for line in (self.root / "commands.jsonl").read_text().splitlines()]

    def test_check_does_not_mutate_services_or_create_data(self):
        result = self.deploy("--check")
        self.assertEqual((result.returncode, len(self.commands()), (self.root / "var").exists()),
                         (0, 3, False))

    def test_success_backs_up_before_migration_and_checks_health(self):
        result = self.deploy()
        commands = [" ".join(command) for command in self.commands()]
        backup = next(i for i, command in enumerate(commands) if "pg_dump" in command)
        migration = next(i for i, command in enumerate(commands) if "doctrine:migrations:migrate" in command)
        backup_dir = next((self.root / "var/production/backups").iterdir())
        self.assertEqual(
            (result.returncode, backup < migration,
             (backup_dir / "database.dump").read_text(),
             (backup_dir / "avatars.tar.gz").read_text(),
             any("--wait --wait-timeout 120 nginx" in command for command in commands),
             (self.root / "var/production/deploy.lock").exists()),
            (0, True, "fake-dump\n", "fake-avatar-archive\n", True, False)
        )

    def test_failed_build_never_stops_web_or_runs_migration(self):
        result = self.deploy(fail="build")
        self.assertEqual((result.returncode, any("stop" in c or "run" in c for c in self.commands()),
                          (self.root / "var/production/deploy.lock").exists()), (11, False, False))

    def test_failed_backup_stops_deployment_before_migration(self):
        result = self.deploy(fail="backup")
        commands = [" ".join(command) for command in self.commands()]
        self.assertEqual((result.returncode,
                          any("doctrine:migrations:migrate" in c for c in commands),
                          sum("stop nginx php" in c for c in commands),
                          len(list(self.root.glob("var/production/backups/*/database.dump.partial")))),
                         (12, False, 2, 1))

    def test_failed_migration_never_starts_web_and_keeps_backup(self):
        result = self.deploy(fail="migration")
        commands = [" ".join(command) for command in self.commands()]
        self.assertEqual((result.returncode,
                          any("--force-recreate" in c for c in commands),
                          len(list(self.root.glob("var/production/backups/*/database.dump")))),
                         (13, False, 1))

    def test_existing_lock_prevents_build(self):
        (self.root / "var/production/deploy.lock").mkdir(parents=True)
        result = self.deploy()
        self.assertEqual((result.returncode,
                          any("build" in c for c in self.commands()),
                          (self.root / "var/production/deploy.lock").is_dir()), (1, False, True))

    def test_missing_env_fails_before_docker(self):
        (self.root / ".env.production").unlink()
        result = self.deploy()
        self.assertEqual((result.returncode, (self.root / "commands.jsonl").exists()), (1, False))


if __name__ == "__main__":
    unittest.main()
