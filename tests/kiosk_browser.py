"""Requires Playwright and a running paired localhost agent with the test fixture P02/12345678."""
import pathlib,sys
from playwright.sync_api import sync_playwright,expect
URL=sys.argv[1] if len(sys.argv)>1 else 'http://127.0.0.1:8765'
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True,args=['--no-sandbox'])
 page=browser.new_page(viewport={'width':800,'height':1080},timezone_id='Europe/Berlin');errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
 page.goto(URL);expect(page.locator('#login')).to_be_visible()
 def identify(card=False):
  if card:
   page.locator('#card').fill('AABBCCDD');page.locator('#card').press('Enter');expect(page.locator('#pin')).to_be_focused()
  else:page.locator('#employee_code').fill('P02')
  page.locator('#pin').fill('12345678');page.get_by_role('button',name='Anmelden',exact=True).click();expect(page.locator('#person')).to_be_visible();expect(page.locator('#name')).to_have_text('audituser')
 identify(True)
 pathlib.Path('/workspace/terminal-previews').mkdir(exist_ok=True)
 page.screenshot(path='/workspace/terminal-previews/kiosk.png',full_page=True)
 page.get_by_role('button',name='Kommen',exact=True).click();expect(page.locator('#message')).to_contain_text('bestätigt');expect(page.locator('#login')).to_be_visible();expect(page.locator('#name')).to_have_text('')
 print('PASS reader-as-keyboard input, PIN login, booking confirmation, immediate identity reset')
 identify();page.get_by_role('button',name='Pause beginnen').click();expect(page.locator('#login')).to_be_visible()
 identify();page.get_by_role('button',name='Gehen',exact=True).click();expect(page.locator('#message')).to_contain_text('bestätigen');page.locator('#confirm').check();page.get_by_role('button',name='Gehen',exact=True).click();expect(page.locator('#login')).to_be_visible();expect(page.locator('#message')).to_contain_text('bestätigt')
 print('PASS pause flow and explicit end-during-pause confirmation')
 identify();expect(page.locator('#person')).to_be_hidden(timeout=12000);expect(page.locator('#name')).to_have_text('');print('PASS ten-second privacy timeout')
 assert page.evaluate('localStorage.length')==0
 assert page.evaluate('sessionStorage.length')==0
 assert 'grant' not in page.content() and 'key' not in page.locator('body').inner_text()
 print('PASS no device key or persistent employee token in browser storage')
 response=page.request.post(URL+'/identify',headers={'Origin':'https://attacker.invalid','X-Kiosk-CSRF':'wrong'},data='{}');assert response.status==403
 response=page.request.get(URL+'/',headers={'Host':'evil.invalid'});assert response.status==403
 print('PASS cross-origin local access and hostile Host blocked')
 page.set_viewport_size({'width':390,'height':844});page.screenshot(path='/workspace/terminal-previews/kiosk-mobile.png',full_page=True)
 assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
 assert not errors,errors
 print('PASS mobile viewport and browser JavaScript execution')
 browser.close()
