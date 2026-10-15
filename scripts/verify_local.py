"""Run PHP checks on a generated isolated PostgreSQL target, without public raw diagnostics."""
import argparse
import json
import os
from pathlib import Path
import secrets
import subprocess
import sys
import time
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parent.parent
POSTGRES = 'postgres:16@sha256:ca0bd484cb98bf4b24eb1010e73fb3fcbd6714d240fbc1a10eea5b7dbecb641d'


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--pagination-only', action='store_true')
    args = parser.parse_args()
    name = 'library-api-review-' + str(int(time.time())) + '-' + secrets.token_hex(4)
    network, database, image = name, name + '-db', name + ':runner'
    output = ROOT / '.local' / name
    output.mkdir(parents=True, mode=0o700)
    env = dict(os.environ, DB_PASSWORD=secrets.token_hex(32))
    env['POSTGRES_PASSWORD'] = env['DB_PASSWORD']
    stage = 'build'
    network_created = database_created = False
    summary = {'complete': False, 'passed': False, 'suites': []}

    def docker(arguments, check=True):
        result = subprocess.run(['docker', *arguments], cwd=ROOT, env=env,
                                capture_output=True, timeout=1200)
        if check and result.returncode:
            raise RuntimeError('Docker operation failed')
        return result

    try:
        docker(['build', '--target', 'integration', '-t', image, '.'])
        stage = 'network'
        docker(['network', 'create', '--internal', network])
        network_created = True
        stage = 'database'
        docker(['run', '-d', '--name', database, '--network', network,
                '-e', 'POSTGRES_DB=library_test', '-e', 'POSTGRES_PASSWORD', POSTGRES,
                '-c', 'timezone=UTC'])
        database_created = True
        for _ in range(30):
            if docker(['exec', database, 'pg_isready', '-h', '127.0.0.1', '-U', 'postgres', '-d', 'library_test'], False).returncode == 0:
                break
            time.sleep(1)
        else:
            raise RuntimeError('Database unavailable')
        suites = [('pagination', 'phpunit.integration.xml', ['--filter', 'PaginationBoundaryTest'])] if args.pagination_only else [
            ('core', 'phpunit.xml', []), ('integration', 'phpunit.integration.xml', [])]
        for label, configuration, extra in suites:
            stage = label
            report = output / (label + '.xml')
            result = docker(['run', '--rm', '--network', network,
                             '--mount', f'type=bind,source={output},target=/diagnostics',
                             '-e', 'DB_HOST=' + database, '-e', 'DB_DATABASE=library_test',
                             '-e', 'DB_USERNAME=postgres', '-e', 'DB_PASSWORD', image,
                             'php', 'vendor/bin/phpunit', '--configuration', configuration,
                             '--log-junit', '/diagnostics/' + report.name, *extra], False)
            cases = list(ET.parse(report).iter('testcase'))
            failed = sum(bool(case.findall('failure') + case.findall('error')) for case in cases)
            record = {'name': label, 'tests': len(cases), 'failures': failed, 'exit_code': result.returncode}
            summary['suites'].append(record)
            print(json.dumps(record), flush=True)
            # The annotator outputs only allowlisted identifiers and source locations.
            if failed:
                subprocess.run([sys.executable, str(ROOT / 'scripts/annotate_tests.py'), str(report)], check=True)
        summary['complete'] = True
        summary['passed'] = all(s['exit_code'] == 0 and s['failures'] == 0 for s in summary['suites'])
    except Exception:
        print('Local API verification failed at stage: ' + stage, file=sys.stderr)
    finally:
        cleanup_ok = True
        if database_created:
            cleanup_ok &= docker(['rm', '-f', '-v', database], False).returncode == 0
        if network_created:
            cleanup_ok &= docker(['network', 'rm', network], False).returncode == 0
        summary['cleanup_completed'] = bool(cleanup_ok)
        (output / 'summary.json').write_text(json.dumps(summary, indent=2) + '\n', encoding='utf-8')
        if not cleanup_ok:
            print('Cleanup required for generated project: ' + name, file=sys.stderr)
    return 0 if summary['passed'] and summary.get('cleanup_completed') else 1


if __name__ == '__main__':
    raise SystemExit(main())
