from __future__ import annotations

import ast
import sys
from pathlib import Path

if len(sys.argv) != 2:
    raise SystemExit('usage: repair_dashboard_boundary.py <plugin-root>')

root = Path(sys.argv[1]).resolve()
account_php = root / 'includes' / 'class-asbo-account-experience.php'
patch_script = Path('staging/v1.2.5/apply_project_hub.py')

if not account_php.is_file():
    raise SystemExit(f'missing account file: {account_php}')
if not patch_script.is_file():
    raise SystemExit(f'missing patch script: {patch_script}')

# Reuse the exact dashboard method string defined by the main patch. This repair
# intentionally replaces everything between render_dashboard() and the next
# known class method, avoiding mixed PHP/HTML brace-scanning ambiguity.
module = ast.parse(patch_script.read_text())
new_dashboard = None
for node in module.body:
    if isinstance(node, ast.Assign):
        for target in node.targets:
            if isinstance(target, ast.Name) and target.id == 'new_dashboard':
                new_dashboard = ast.literal_eval(node.value)
                break
    if new_dashboard is not None:
        break

if not isinstance(new_dashboard, str) or 'asbo-dashboard-project-hub' not in new_dashboard:
    raise SystemExit('could not recover new_dashboard string from main patch')

account = account_php.read_text()
signature = '    public static function render_dashboard(): void {'
start = account.find(signature)
if start < 0:
    raise SystemExit('render_dashboard signature not found after main patch')

marker = '    private static function quick_card('
end = account.find(marker, start)
if end < 0:
    raise SystemExit('quick_card method marker not found after render_dashboard')

account = account[:start] + new_dashboard.rstrip() + '\n\n' + account[end:]
account_php.write_text(account)
print('ASBO 1.2.5 dashboard boundary repaired with explicit next-method marker')
