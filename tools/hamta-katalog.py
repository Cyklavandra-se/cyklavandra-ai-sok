"""Hämtar resor, taggar, evenemang och destinationssidor från cyklavandra.se (öppna REST-API:t, bara läsning).

Användning: python3 tools/hamta-katalog.py <mapp>
Skriver tags.json, trips.json, events.json och dests.json som tools/importera-katalog.php läser.
"""
import json, ssl, sys, os, urllib.request, datetime

OUT = sys.argv[1]
os.makedirs(OUT, exist_ok=True)
CA = '/root/.ccr/ca-bundle.crt'
ctx = ssl.create_default_context(cafile=CA) if os.path.exists(CA) else ssl.create_default_context()


def get(url):
    req = urllib.request.Request(url, headers={'User-Agent': 'cvai-test'})
    with urllib.request.urlopen(req, context=ctx, timeout=60) as r:
        return json.load(r), r.headers


def all_pages(path):
    out, p = [], 1
    while True:
        sep = '&' if '?' in path else '?'
        d, h = get(f'https://cyklavandra.se/wp-json/wp/v2/{path}{sep}per_page=100&page={p}')
        out += d
        if p >= int(h.get('X-WP-TotalPages', '1')):
            return out
        p += 1


tags = all_pages('tags?_fields=id,name,slug,count')
ids = ','.join(str(t['id']) for t in tags if t['slug'] in ('guidad', 'sjalvguidad'))
trips = all_pages(f'pages?tags={ids}&_fields=id,title,tags,excerpt,content,link,parent')
today = datetime.date.today().isoformat()
events, p = [], 1
while True:
    d, _ = get(f'https://cyklavandra.se/wp-json/tribe/events/v1/events?start_date={today}&per_page=50&page={p}')
    events += d.get('events', [])
    if p >= int(d.get('total_pages', 1)):
        break
    p += 1
root, _ = get('https://cyklavandra.se/wp-json/wp/v2/pages?slug=destinationer&_fields=id,link')
root_id = [x for x in root if 'hitta-resa/destinationer' in x['link']][0]['id']
dests, queue = [], [(root_id, '')]
while queue:
    pid, pt = queue.pop(0)
    kids, _ = get(f'https://cyklavandra.se/wp-json/wp/v2/pages?parent={pid}&per_page=100&_fields=id,title,excerpt,content,link,parent')
    for k in kids:
        dests.append(dict(k, parent_title=pt))
        queue.append((k['id'], k['title']['rendered']))
for name, data in (('tags', tags), ('trips', trips), ('events', events), ('dests', dests)):
    json.dump(data, open(os.path.join(OUT, name + '.json'), 'w'))
print(f'{len(trips)} resor, {len(events)} evenemang, {len(dests)} destinationer, {len(tags)} taggar')
