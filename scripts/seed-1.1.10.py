#!/usr/bin/env python3
from pathlib import Path
import sys

if len(sys.argv) != 2:
    raise SystemExit('usage: seed-1.1.10.py <plugin-folder>')
root=Path(sys.argv[1])
main=root/'all-star-bulk-order-block.php'
block=root/'block'/'block.json'
readme=root/'README.txt'
review=root/'includes'/'class-asbo-artwork-review.php'

s=main.read_text()
for old,new in [(' * Version: 1.1.9',' * Version: 1.1.10'),("    private const VERSION = '1.1.9';","    private const VERSION = '1.1.10';")]:
    if s.count(old)!=1: raise SystemExit(f'marker mismatch: {old}')
    s=s.replace(old,new,1)
main.write_text(s)

b=block.read_text()
if b.count('"version": "1.1.9"')!=1: raise SystemExit('block version marker mismatch')
block.write_text(b.replace('"version": "1.1.9"','"version": "1.1.10"',1))

r=readme.read_text().replace('All Star Bulk Order Block v1.1.9','All Star Bulk Order Block v1.1.10',1)
r += '''\n\nv1.1.10 Impeccable-style artwork polish:\n- Restyles only the customer Artwork component and WooCommerce ASBO Artwork Review panel.\n- Uses All Star Embroidery navy, muted warm gold, white, and quiet neutral surfaces; semantic green/red remain reserved for actual approval/change states.\n- Reduces nested cards, shadows, and competing borders while strengthening type hierarchy and spacing.\n- Improves file rows, review timeline, form focus states, action hierarchy, and responsive stacking.\n- Makes the admin panel feel native to WooCommerce with restrained All Star brand cues.\n- Upload, approval, email, order-note, artwork status, pricing, Supplier Sync, cart, and checkout logic are unchanged.\n'''
readme.write_text(r)

CUSTOMER='''
            /* ASBO v1.1.10 — Impeccable-style All Star polish */
            .asbo-artwork-customer{--asbo-navy:#11192d;--asbo-gold:#d7aa32;--asbo-gold-soft:#fbf7eb;--asbo-muted:#667085;--asbo-line:#e4e7ec;position:relative;border-color:var(--asbo-line);border-radius:12px;box-shadow:none;color:#20283a;overflow:hidden}
            .asbo-artwork-customer:before{content:"";position:absolute;inset:0 auto auto 0;width:88px;height:3px;background:var(--asbo-gold)}
            .asbo-artwork-customer__heading{gap:20px;margin-bottom:18px}.asbo-artwork-customer__eyebrow{margin-bottom:5px;color:#7a8190;font-size:11px;letter-spacing:.11em}.asbo-artwork-customer h2{color:var(--asbo-navy);font-size:clamp(27px,3vw,35px);font-weight:750;letter-spacing:-.025em;line-height:1.08}
            .asbo-artwork-status{min-height:27px;padding:4px 10px;border:1px solid transparent;font-size:11px;letter-spacing:.015em}.asbo-artwork-status--needed{background:#f1f3f6;color:#536174}.asbo-artwork-status--awaiting_review{background:var(--asbo-gold-soft);color:#725600;border-color:#ead89d}.asbo-artwork-status--changes_requested{background:#fff3ef;color:#9a3d2c;border-color:#efc5ba}.asbo-artwork-status--approved{background:#eef8f2;color:#1f6b45;border-color:#c8e6d3}
            .asbo-artwork-customer__intro{max-width:760px;margin-bottom:24px;color:var(--asbo-muted);font-size:clamp(14px,1.15vw,16px);line-height:1.65}
            .asbo-artwork-state{gap:14px;margin:18px 0 24px;padding:15px 16px;border:0;border-left:3px solid var(--asbo-gold);border-radius:0 9px 9px 0;background:var(--asbo-gold-soft)}.asbo-artwork-state__icon{width:30px;flex-basis:30px;border:1px solid rgba(17,25,45,.08);background:#fff;color:var(--asbo-navy)}.asbo-artwork-state strong{color:var(--asbo-navy);font-size:15px}.asbo-artwork-state p{color:var(--asbo-muted)}.asbo-artwork-state--changes{border-left-color:#c8694f;background:#fff7f4}.asbo-artwork-state--approved{border-left-color:#399063;background:#f4faf6}
            .asbo-artwork-files,.asbo-artwork-reference{margin-bottom:24px;padding:0;background:transparent}.asbo-artwork-files>strong,.asbo-artwork-reference>strong{color:var(--asbo-navy);font-size:13px}.asbo-artwork-files__list{gap:0;border-top:1px solid var(--asbo-line)}.asbo-artwork-file{gap:11px;padding:11px 2px;border:0;border-bottom:1px solid var(--asbo-line);border-radius:0;background:transparent}.asbo-artwork-file__icon{display:flex;align-items:center;justify-content:center;flex:0 0 28px;width:28px;height:28px;border-radius:7px;background:#f8f9fb;color:var(--asbo-navy)}.asbo-artwork-file b{color:var(--asbo-navy)}.asbo-artwork-file small{color:#8a91a0}.asbo-artwork-reference{padding:14px 16px;border-left:3px solid var(--asbo-gold);border-radius:0 8px 8px 0;background:var(--asbo-gold-soft)}
            .asbo-artwork-field>span{color:var(--asbo-navy);font-size:13px}.asbo-artwork-field input[type=file],.asbo-artwork-field textarea{border-radius:8px;transition:border-color .16s ease,box-shadow .16s ease}.asbo-artwork-field input[type=file]:focus,.asbo-artwork-field textarea:focus{outline:0;border-color:var(--asbo-gold);box-shadow:0 0 0 3px rgba(215,170,50,.16)}.asbo-artwork-field small{color:#7a8190}
            .asbo-artwork-primary{min-height:45px;border-color:#bd9122;border-radius:8px;background:#e2b536;color:var(--asbo-navy);font-weight:800;box-shadow:none;transition:background .16s ease,border-color .16s ease,transform .16s ease}.asbo-artwork-primary:hover{background:#d7aa32;border-color:#ad821b}.asbo-artwork-primary:focus-visible{outline:0;box-shadow:0 0 0 3px rgba(215,170,50,.22)}
            .asbo-artwork-replace,.asbo-artwork-history{border-top-color:var(--asbo-line)}.asbo-artwork-replace>summary,.asbo-artwork-history>summary{color:var(--asbo-navy);font-size:13px}.asbo-artwork-history ol{padding-left:3px}.asbo-artwork-history li{position:relative;gap:12px;padding-bottom:16px}.asbo-artwork-history li:not(:last-child):after{content:"";position:absolute;left:4px;top:15px;bottom:0;width:1px;background:var(--asbo-line)}.asbo-artwork-history__dot{position:relative;z-index:1;border:2px solid #fff;background:var(--asbo-gold);box-shadow:0 0 0 1px var(--asbo-gold)}.asbo-artwork-history strong{color:var(--asbo-navy);font-size:13px}.asbo-artwork-history p{color:var(--asbo-muted)}.asbo-artwork-stitch-note{border-top-color:var(--asbo-line);color:#747c8c}.asbo-artwork-stitch-note strong{color:var(--asbo-navy)}
            @media(max-width:600px){.asbo-artwork-customer{margin:24px 0;padding:20px 18px;border-radius:10px}.asbo-artwork-customer:before{width:64px}.asbo-artwork-customer__heading{gap:10px}.asbo-artwork-primary{width:100%}}
'''

ADMIN='''
            /* ASBO v1.1.10 — Impeccable-style All Star admin polish */
            .asbo-review-admin{--navy:#11192d;--gold:#d7aa32;--gold-soft:#fbf7eb;--muted:#667085;--line:#e2e6ec;position:relative;padding:22px;color:#20283a;background:#fff;border-top:3px solid var(--gold)}.asbo-review-admin h3,.asbo-review-admin h4{color:var(--navy)}
            .asbo-review-admin__top{align-items:flex-start;gap:18px;margin-bottom:20px}.asbo-review-admin__top h3{margin-top:3px;font-size:21px;font-weight:650;letter-spacing:-.01em}.asbo-review-admin__eyebrow{color:#7a8190;font-size:10px;letter-spacing:.12em}
            .asbo-review-admin__grid{grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:0;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}.asbo-review-admin__files,.asbo-review-admin__details{padding:18px;border:0;border-radius:0}.asbo-review-admin__details{border-left:1px solid var(--line);background:#fbfbfa}.asbo-review-admin h4{font-size:13px;font-weight:800}
            .asbo-review-file-card{grid-template-columns:88px minmax(0,1fr);gap:13px;padding:12px 0;border:0;border-bottom:1px solid var(--line);border-radius:0;background:transparent}.asbo-review-file-card+.asbo-review-file-card{margin-top:0}.asbo-review-file-card:last-child{border-bottom:0}.asbo-review-file-card__preview,.asbo-review-file-card__document{width:88px;height:70px;border:1px solid var(--line);border-radius:7px}.asbo-review-file-card__document{background:#f8f9fa;color:#687184;font-size:10px;letter-spacing:.08em}.asbo-review-file-card strong{color:var(--navy);word-break:break-word}.asbo-review-file-card small{color:#858c9a}.asbo-review-file-card a:not(.asbo-review-file-card__preview){color:#36567c;font-weight:600;text-decoration:none}
            .asbo-review-admin dl>div{grid-template-columns:105px 1fr;padding:8px 0;border-bottom-color:#eaedf1}.asbo-review-admin dt{color:#7b8391}.asbo-review-admin dd{color:var(--navy);font-weight:650}.asbo-review-note,.asbo-review-stitch,.asbo-review-empty{margin-top:16px;padding:13px 14px;border:0;border-radius:8px;background:#f5f6f7}.asbo-review-note strong,.asbo-review-stitch strong,.asbo-review-empty strong{color:var(--navy)}.asbo-review-stitch{border-left:3px solid var(--gold);border-radius:0 8px 8px 0;background:var(--gold-soft)}
            .asbo-review-admin__history{margin-top:22px;padding:18px 2px 0;border-top-color:var(--line)}.asbo-review-admin__history ol{padding-left:2px}.asbo-review-admin__history li{position:relative;gap:12px;padding-bottom:15px}.asbo-review-admin__history li:not(:last-child):after{content:"";position:absolute;left:4px;top:14px;bottom:0;width:1px;background:var(--line)}.asbo-review-admin__history li>span{position:relative;z-index:1;border:2px solid #fff;background:var(--gold);box-shadow:0 0 0 1px var(--gold)}.asbo-review-admin__history strong{color:var(--navy)}.asbo-review-admin__history small{color:#858c9a}
            .asbo-review-admin__actions{gap:18px;margin:20px -22px -22px;padding:18px 22px;border-top:1px solid var(--line);background:#f8f8f7}.asbo-review-admin__actions .button{min-height:36px;border-radius:6px;box-shadow:none;font-weight:650}.asbo-review-approve{background:var(--navy)!important;border-color:var(--navy)!important;color:#fff!important}.asbo-review-approve:hover{background:#1a2743!important;border-color:#1a2743!important}.asbo-review-change-form{max-width:720px}.asbo-review-change-form label span{color:var(--navy);font-size:12px;font-weight:750}.asbo-review-change-form textarea{border-color:#cfd5df;border-radius:6px;box-shadow:none}.asbo-review-change-form textarea:focus{border-color:var(--gold);box-shadow:0 0 0 1px var(--gold)}.asbo-review-changes{border-color:#caa02f!important;background:#fff!important;color:#674f0d!important}.asbo-review-changes:hover{border-color:#a77e15!important;background:var(--gold-soft)!important;color:#4b3a0d!important}
            .asbo-artwork-status{min-height:25px;padding:3px 9px;border:1px solid transparent;font-size:10px;letter-spacing:.02em}.asbo-artwork-status--awaiting_review{background:var(--gold-soft);color:#725600;border-color:#ead89d}
            @media(max-width:1000px){.asbo-review-admin__grid{grid-template-columns:1fr}.asbo-review-admin__details{border-left:0;border-top:1px solid var(--line)}}
            @media(max-width:600px){.asbo-review-admin{padding:16px}.asbo-review-admin__top{flex-direction:column;gap:9px}.asbo-review-admin__files,.asbo-review-admin__details{padding:14px}.asbo-review-file-card{grid-template-columns:70px minmax(0,1fr)}.asbo-review-file-card__preview,.asbo-review-file-card__document{width:70px;height:58px}.asbo-review-admin dl>div{grid-template-columns:1fr;gap:2px}.asbo-review-admin__actions{margin:18px -16px -16px;padding:16px}.asbo-review-admin__actions form:first-child,.asbo-review-admin__actions form:first-child .button{width:100%}}
'''

text=review.read_text()
if 'ASBO v1.1.10' in text: raise SystemExit('v1.1.10 polish already present')

def inject(function_name, css):
    global text
    start=text.index(f'private static function {function_name}')
    end=text.index('</style>',start)
    text=text[:end]+css+text[end:]

inject('customer_styles()', CUSTOMER)
inject('admin_styles()', ADMIN)
review.write_text(text)
print('ASBO v1.1.10 Impeccable-style polish applied')
