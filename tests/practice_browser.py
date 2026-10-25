"""Optional Playwright smoke test; disposable seed.php HTTP fixture only."""
import pathlib,sys
from playwright.sync_api import sync_playwright,expect
base=sys.argv[1];output=pathlib.Path(sys.argv[2]) if len(sys.argv)>2 else pathlib.Path('/tmp/chrono-previews');output.mkdir(exist_ok=True)
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True,args=['--no-sandbox'])
 context=browser.new_context(viewport={'width':1280,'height':900},timezone_id='Europe/Berlin');page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
 page.goto(base+'/login.php');page.locator('[name=username]').fill('audituser');page.locator('[name=password]').fill('LocalAudit!Password2026');page.locator('button[type=submit]').click();page.wait_for_url('**/attendance.php')
 expect(page.get_by_role('button',name='Pause beginnen')).to_be_hidden();page.get_by_role('button',name='Kommen',exact=True).click();expect(page.locator('#status')).to_have_text('Arbeitszeit läuft')
 expect(page.locator('#recent-records')).to_contain_text('Läuft')
 page.get_by_role('button',name='Pause beginnen').click();expect(page.locator('#status')).to_have_text('Pause läuft')
 page.on('dialog',lambda dialog:dialog.accept());page.get_by_role('button',name='Gehen',exact=True).click();expect(page.locator('#status')).to_have_text('Nicht angemeldet');expect(page.get_by_role('button',name='Gehen',exact=True)).to_be_hidden();expect(page.locator('#message')).to_contain_text('bestätigt')
 expect(page.locator('#recent-records')).not_to_contain_text('Läuft')
 print('PASS browser work/break/end flow and live record refresh')
 # A second tab makes the first tab stale. A 409 must reconcile the visible controls.
 second=page.context.new_page();second.goto(base+'/attendance.php');second.get_by_role('button',name='Kommen',exact=True).click();expect(second.locator('#status')).to_have_text('Arbeitszeit läuft')
 page.get_by_role('button',name='Kommen',exact=True).click();expect(page.locator('#message')).to_contain_text('offene Arbeitszeit');expect(page.locator('#status')).to_have_text('Arbeitszeit läuft')
 page.get_by_role('button',name='Gehen',exact=True).click();expect(page.locator('#status')).to_have_text('Nicht angemeldet');second.close()
 print('PASS stale tab conflict refreshes state and allows ending the actual session')
 page.locator('[name=urlaubStart]').fill('2036-01-08');page.locator('[name=urlaubEnde]').fill('2036-01-08');page.get_by_role('button',name='Abwesenheit erfassen').click();expect(page.locator('#absence-message')).to_contain_text('nach Arbeitszeitmodell')
 print('PASS absence form returns inline confirmation')
 page.screenshot(path=str(output/'attendance.png'),full_page=True)
 for path,title in [('work_models.php','Arbeitszeitmodelle'),('history.php','Änderungshistorie'),('adjustments.php','Korrekturanträge')]:
  page.goto(base+'/'+path);expect(page.locator('h1')).to_have_text(title);page.screenshot(path=str(output/(path+'.png')),full_page=True)
  page.set_viewport_size({'width':390,'height':844});assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'),path
  page.set_viewport_size({'width':1280,'height':900})
 assert not errors,errors
 print('PASS models/history/corrections and mobile width without JS errors')
 browser.close()
