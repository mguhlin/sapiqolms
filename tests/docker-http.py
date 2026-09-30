import urllib.request,urllib.parse,http.cookiejar,re,json
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
base='http://127.0.0.1:8080'
html=client.open(base+'/login').read().decode();csrf=re.search(r'name="_csrf" value="([^"]+)"',html)[1]
form=urllib.parse.urlencode({'email':'admin@example.org','password':'Isolated-test-password-123','_csrf':csrf}).encode()
assert client.open(base+'/login',form).status==200
who=json.load(client.open(base+'/api/whoami'));assert who['authenticated'] is True
assert client.open(base+'/admin/readiness').status==200
print('Docker/Apache installation, administrator login and readiness page passed.')
