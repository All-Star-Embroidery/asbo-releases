from pathlib import Path

path = Path('staging/v1.3.1-rc.2/apply_v131_rc2.py')
text = path.read_text(encoding='utf-8')
old = "wp_register_style( 'asbo-account-experience', false, array(), '1.3.0' );"
new = "wp_register_style( 'asbo-account-experience', false, array(), '1.3.1-rc.2' );"
if old not in text:
    if new in text:
        print('RC2 patch matcher already corrected')
    else:
        raise SystemExit('Expected account style matcher not found in RC2 patch script')
else:
    path.write_text(text.replace(old, new, 1), encoding='utf-8')
    print('Corrected RC2 account style matcher')
