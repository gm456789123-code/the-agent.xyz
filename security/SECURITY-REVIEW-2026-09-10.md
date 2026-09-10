# ผลตรวจความปลอดภัยก่อนปล่อย — 10 กันยายน 2026

**แก้แล้วตาม [รายงานการแก้ล่าสุด](SECURITY-FIXES-2026-09-10.md)** — เนื้อหาด้านล่างเก็บหลักฐานก่อนแก้

**มีผลตรวจใหม่หลัง Gemini ทำงานเสร็จ:** อ่าน [รายงานตรวจซ้ำ](SECURITY-RETEST-2026-09-10.md) สำหรับสถานะล่าสุด รายงานด้านล่างเก็บหลักฐานรอบแรก

**ผลประเมิน: ยังไม่ควรเปิดให้บุคคลทั่วไปเข้าถึงระบบด้วยการตั้งค่าปัจจุบัน**

ตรวจซอร์สโค้ด Astro, WordPress mu-plugins, Express webhook, Docker/phpMyAdmin; เรียก HTTP แบบอ่านอย่างเดียวกับบริการ local; ทดสอบช่องโหว่ด้วยโค้ด router/callback จริงและข้อมูลจำลอง; ตรวจ dependency ผ่าน npm audit

ยังไม่ได้แก้โค้ดแอปหรือเปลี่ยนการตั้งค่าบริการ รายงานนี้เป็นการประเมินระบบ local ไม่ใช่ใบรับรองความปลอดภัยของ deployment ที่จะเปิดจริง

**อัปเดตระหว่างตรวจซ้ำ:** มีผู้แก้ `nexus-auth.php`, `nexus-orders.php`, `nexus-settings.php` พร้อมกับการตรวจนี้ ตาราง HTTP ด้านล่างเป็นหลักฐานก่อนการแก้; รอบสุดท้าย WordPress ตอบ 500 ขณะที่ frontend/phpMyAdmin ยังตอบ 200 พบสาเหตุที่ทำซ้ำได้: auth ประกาศ `nexus_get_client_ip()` ก่อน แล้ว orders ประกาศชื่อเดียวกันแบบไม่มี guard ทำให้ PHP fatal `Cannot redeclare function nexus_get_client_ip()` แต่ละไฟล์ผ่าน `php -l` แยกกัน จึงต้องตรวจ bootstrap รวมด้วย ยังไม่ได้แก้ทับงานที่กำลังทำจากภายนอก

การแก้พร้อมกันเพิ่ม rate limiter และ origin allowlist แต่ยังคง anonymous order permission, การค้นด้วยเบอร์โทร และ header CORS ที่ไม่รวม `X-Nexus-Token` ใน diff ที่ตรวจ จึงยังไม่ถือว่าแก้ช่องโหว่หลักสำเร็จ เลขบรรทัดในรายละเอียดอ้างอิงโค้ดก่อนการแก้พร้อมกัน

ต่อมามีการเพิ่มเงื่อนไข `post_status=publish` และเปลี่ยน masking ใน recent feed ระหว่างรัน harness อีกครั้ง ทำให้ assertion ที่คาดว่าซื้อ draft สำเร็จไม่ผ่าน แปลว่าข้อ 7–8 มีการแก้ที่กำลังดำเนินอยู่ ไม่ควรอ้างว่ายังโจมตีได้เหมือน baseline หรือว่าปิดครบแล้วโดยไม่มีการตรวจซ้ำหลังโค้ดนิ่ง รายงานและคำสั่ง `--baseline` จึงตรึงหลักฐานเดิมกับ Git blob `6895621e3d664dcd5921d6b2fcb29e801c114a18`

นอกจากนี้ limiter ใหม่เชื่อ `CF-Connecting-IP`/`X-Forwarded-For` โดยไม่ตรวจว่า request มาจาก trusted proxy หากเข้าถึง origin ได้ตรง ผู้เรียกสามารถเปลี่ยน header เพื่อเปลี่ยน rate-limit key ได้ ข้อนี้เป็นผลตรวจโค้ด ยังไม่ได้ทดสอบข้าม limiter ผ่าน HTTP เพราะ backend ตอบ 500

## สภาพแวดล้อมที่ยืนยัน

| ส่วน | หลักฐาน |
| --- | --- |
| หน้าเว็บ | `localhost:4321` ตอบ 200 และมี `/@vite/client`: เป็น dev server |
| API ที่หน้าเว็บใช้ | module ที่ dev server ส่งมี `PUBLIC_WP_API_URL=http://localhost:8080/wp-json` |
| WordPress | `localhost:8080/wp-json/nexus/v1` ตอบ 200 และประกาศ routes สมาชิก/ออเดอร์/สินค้า |
| phpMyAdmin | `localhost:8082` ตอบ 200 พร้อม authenticated navigation โดย request ไม่ส่ง cookie หรือ credentials |
| Docker | `docker-phpmyadmin-1` publish `0.0.0.0:8082` และ `[::]:8082`; WordPress publish 8080 |
| Express webhook | `localhost:4000/health` เชื่อมต่อไม่ได้ระหว่างตรวจ; ไม่อยู่ใน Compose ปัจจุบัน |
| รูปแบบ build | `apps/web/astro.config.mjs` ใช้ `output: static`; Node adapter อยู่ใน dependency แต่ไม่ได้ตั้งใช้ |

การ bind ทุก interface ไม่ได้ยืนยันว่าเข้าถึงจากอินเทอร์เน็ตได้จริง: ยังขึ้นกับ firewall/NAT การตรวจนี้ไม่ได้สแกนเครือข่ายภายนอก และไม่ได้ทดสอบ container ของโปรเจกต์อื่นที่พบในเครื่อง

## ช่องโหว่ที่พบ

ระดับความรุนแรงเป็นการจัดลำดับแก้ตามผลกระทบและเงื่อนไขของโปรเจกต์ ไม่ใช่คะแนน CVSS ที่คำนวณใหม่

### 1. Critical — phpMyAdmin เข้าจัดการฐานข้อมูลโดยไม่ต้องล็อกอิน

- หลักฐาน: GET `http://localhost:8082/` แบบไม่มี cookie ได้ UI ที่มี `pma_navigation` และไม่มีช่อง `pma_username`
- ต้นเหตุ: `docker/phpmyadmin/config.user.inc.php:4` ตั้ง `auth_type=config` และใช้ user/password จาก environment; `docker/docker-compose.yml:48` publish port โดยไม่จำกัด loopback
- ผลกระทบ: ผู้ที่เข้าถึงพอร์ตได้สามารถใช้สิทธิ์ของบัญชีฐานข้อมูลที่ตั้งไว้ผ่าน phpMyAdmin ไม่ได้ยืนยันสิทธิ์ root หรือทดลองแก้/ลบข้อมูล
- แก้ก่อนปล่อย: เลิก auto-login, ใช้การล็อกอินจริง, จำกัดพอร์ตที่ `127.0.0.1` หรือเครือข่ายผู้ดูแล และไม่เปิดเครื่องมือ DB admin เป็น public service
- หมายเหตุ: ไฟล์ config นี้ถูก `.gitignore` ยกเว้นไว้ แต่มีอยู่จริงและ Compose mount ใช้งาน

### 2. Critical เมื่อเปิดบริการ — เปลี่ยนออเดอร์เป็น paid โดยไม่ชำระเงิน

- ต้นเหตุ: `apps/webhook-service/src/routes/webhook.js:8` รับเพียง `orderId` แล้ว UPDATE เป็น `paid` ที่บรรทัด 16 โดยไม่ตรวจตัวตน ลายเซ็น ผู้ให้บริการ ยอดเงิน หรือธุรกรรม
- ยืนยันด้วย Express router จริงและฐานข้อมูลจำลอง: POST ที่ไม่มี credential หรือหลักฐานชำระเงินได้ 200 และสถานะ fixture เปลี่ยนเป็น paid
- ยังไม่ได้ยืนยันการส่งสินค้าอัตโนมัติหรือการเชื่อมต่อกับออเดอร์ WordPress: Node ใช้ตาราง `orders` แยกจาก WordPress CPT
- แก้ก่อนเปิดบริการ: ปิด endpoint ชั่วคราวจนมีการยืนยันธุรกรรมกับผู้ให้บริการ ตรวจยอดเงิน/ผู้รับ/ออเดอร์ ตรวจลายเซ็นและ freshness และบังคับ transaction ID ไม่ซ้ำ

### 3. High — ค้นประวัติซื้อของผู้อื่นจากเบอร์โทรโดยไม่ยืนยันเจ้าของ

- `docker/wp-content/mu-plugins/nexus-orders.php:78` เปิด `/orders/track` เป็น public และใช้เบอร์โทรหรือรหัสออเดอร์เป็นเงื่อนไขค้นหาเพียงอย่างเดียว
- ชุด PHP จำลองเรียก callback จริงโดยไม่มี session และคืนรหัสออเดอร์ สินค้า ยอด สถานะ และเวลา จากเบอร์โทร fixture
- Live negative control: query รหัสที่ไม่มีอยู่ตอบ 404 ไม่ใช่ 401; ไม่ได้ค้นเบอร์ของลูกค้าจริง
- รหัสออเดอร์สร้างด้วยท้าย `uniqid()` เพียง 6 ตัว (`:24`) จึงไม่ควรใช้เป็นความลับสำหรับให้สิทธิ์อ่านข้อมูล
- แก้: ผูกออเดอร์กับ user ID และตรวจ ownership หรือใช้ลิงก์ติดตามที่มี random token ยาวและหมดอายุ; การค้นด้วยเบอร์โทรควรต้องยืนยันเจ้าของ พร้อมจำกัดความถี่

### 4. High — เงื่อนไขต้องล็อกอินก่อนซื้ออยู่เฉพาะหน้าเว็บ

- หน้าเว็บตรวจว่ามีข้อความใน localStorage (`apps/web/src/components/BuyOrderModal.astro:75`) แต่คำขอสร้างออเดอร์ไม่ส่ง token (`:110`)
- Backend `nexus-orders.php:30` ใช้ `__return_true` และ callback ไม่ตรวจผู้ใช้หรือผูกเจ้าของออเดอร์
- ชุดทดสอบ callback จริงสร้างออเดอร์สำเร็จโดยไม่มี credentials
- ผลกระทบ: ข้ามข้อกำหนดสมาชิก สร้างคำสั่งซื้อในชื่อเบอร์คนอื่น และเสี่ยงออเดอร์ขยะ; ไม่ใช่หลักฐานว่าปลอม localStorage แล้วได้สิทธิ์ผู้ดูแล
- แก้: ตรวจ token ฝั่ง API, บันทึกเจ้าของออเดอร์, ตรวจเบอร์ที่ผู้ใช้มีสิทธิ์ใช้ และจำกัดความถี่การสร้าง

### 5. High เมื่อเปิดบริการ — Node รับราคาที่ผู้เรียกกำหนดเอง

- `apps/webhook-service/src/routes/orders.js:7` รับ `priceCents` และเช็คเพียง truthiness
- ยืนยัน HTTP 201 สำหรับราคา 1 และ -100 ด้วย router จริง/ฐานข้อมูลจำลอง
- แก้: อ่านราคาจากแหล่งสินค้าที่เชื่อถือได้ฝั่ง server และตรวจชนิด/ขอบเขต; ไม่รับชื่อหรือราคาจาก client เป็นค่าที่ใช้คิดเงินจริง
- WordPress route อ่านราคาจาก product meta อยู่แล้ว; ข้อนี้เจาะจง Node service

### 6. High เมื่อเปิดบริการ — Node อ่านออเดอร์อื่นและข้อมูลติดต่อได้

- `apps/webhook-service/src/routes/orders.js:22` ใช้ ID ค้นหาและส่ง `SELECT *` โดยไม่ตรวจสิทธิ์
- ยืนยัน GET `/orders/1` ไม่มี credential ได้ 200 และ `customer_contact` ของ fixture
- แก้: ยืนยันผู้ใช้/ตรวจ ownership และคืนเฉพาะ field ที่จำเป็น การเปลี่ยนเป็น ID ที่เดายากอย่างเดียวไม่แก้เรื่องสิทธิ์

### 7. Medium — endpoint ออเดอร์ล่าสุดเผยเบอร์ 9 หลักเต็ม

- การสร้างออเดอร์ยอมรับ 9 หรือ 10 หลัก (`nexus-orders.php:39`) แต่ masking รองรับเฉพาะ 10 หลัก (`:133`)
- callback `/orders/recent` คืนเบอร์ fixture `000000000` ครบทุกหลัก ส่วน fixture 10 หลักถูก mask
- แม้เบอร์ 10 หลักก็เปิด 7 หลัก เหลือเพียง 3 หลักที่ปิดไว้ ซึ่งเพิ่มความเสี่ยงเมื่อใช้ร่วมกับการค้นประวัติด้วยเบอร์
- แก้: ทำ normalization/validation ให้ตรงกัน และให้ masking ปิดข้อมูลโดย default แม้รูปแบบไม่ตรง; ลดข้อมูลส่วนตัวใน feed สาธารณะ

### 8. Medium — ซื้อสินค้าที่ยังเป็น draft ได้

- `nexus-orders.php:44` ตรวจแค่ post type ไม่ตรวจ `post_status`
- callback จริงรับ product fixture สถานะ draft แล้วคืนชื่อ ราคา และสร้างออเดอร์
- แก้: ยอมรับเฉพาะสินค้า publish และพร้อมขาย รวมถึงตรวจราคา/stock ตามกติกาธุรกิจ

### 9. Medium — logout ข้าม origin ไม่สามารถส่ง header ที่ใช้เพิกถอน token

- หน้าเว็บส่ง `X-Nexus-Token` (`apps/web/src/components/Header.astro:312`) แต่ `nexus-settings.php:14` ไม่ประกาศ header นี้ใน CORS
- Live OPTIONS จาก origin `http://localhost:4321` ได้ `Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Type, Origin, Accept` โดยไม่มี `X-Nexus-Token`
- ตามกติกา CORS เบราว์เซอร์จะไม่ส่ง POST นี้; client กลับลบ localStorage และกลืน error จึงแสดงเหมือน logout สำเร็จ ขณะที่ server token อาจยังมีอายุสูงสุด 30 วัน
- หลักฐานคือ preflight และเส้นทางโค้ด ยังไม่ได้ทดสอบ logout ด้วยบัญชีจริงใน browser
- แก้: ใช้ `Authorization: Bearer` ที่ backend รองรับ หรืออนุญาต header ให้ตรงกัน จำกัด origin และทดสอบว่า token เดิมใช้ `/me` ไม่ได้หลัง logout

### 10. Dependency advisories — 8 แพ็กเกจที่ npm audit รายงาน

`npm audit --json` ติดต่อ registry สำเร็จและจบ exit 1 เพราะพบคำเตือน: critical 1, high 2, moderate 5 รวม 8 package entries ไม่ใช่ช่องโหว่ที่โจมตีสำเร็จ 8 จุด

| แพ็กเกจ | เวอร์ชันใน lockfile | ระดับรวมจาก audit |
| --- | --- | --- |
| astro | 4.16.19 | critical |
| sharp | 0.33.5 | high |
| vite | 5.4.21 | high |
| @astrojs/node | 8.3.4 | moderate |
| express | 4.22.2 | moderate |
| qs | 6.15.3 | moderate |
| esbuild | 0.21.5 | moderate |
| body-parser | dependency ทางอ้อม | moderate |

Astro advisory ระบุความเสี่ยง RCE เมื่อผู้โจมตีทำให้ระบบประมวลผล AVIF ที่ไม่น่าเชื่อถือด้วย Sharp; ยังไม่ได้พิสูจน์ว่าแอปนี้มีเส้นทางรับภาพเช่นนั้น และไม่ได้ส่งภาพ exploit: [ประกาศของ Astro](https://github.com/withastro/astro/security/advisories/GHSA-26w7-cxv4-gfx2)

Vite มี advisory การข้ามข้อจำกัดอ่านไฟล์บน Windows ซึ่งเกี่ยวข้องกับสภาพแวดล้อม dev ปัจจุบัน แต่ยังไม่ได้ทดลองขโมยไฟล์: [ประกาศของ Vite](https://github.com/vitejs/vite/security/advisories/GHSA-fx2h-pf6j-xcff)

ประเมินการอัปเกรดพร้อม compatibility/build tests; ไม่ใช้ `npm audit fix --force` โดยอัตโนมัติ เพราะมี major upgrades และ adapter บางตัวไม่ได้ถูกใช้งานจริง

## จุดที่ควรตรวจต่อ ไม่ได้นับว่าโจมตีสำเร็จ

- HTML บทความจาก WordPress ใช้ `set:html` ใน ArticleGrid และหน้า article; ต้องตรวจว่า role ที่ไม่ควรใส่ script สามารถสร้าง HTML อันตรายผ่าน CMS ได้หรือไม่ ไม่มีหลักฐาน stored XSS สำเร็จจากรอบนี้
- token เก็บเป็น plaintext ใน user meta และ localStorage; ต้องทบทวนการ revoke หลังเปลี่ยนรหัสผ่าน/ปิดบัญชี และไม่ยอมรับ token ที่ไม่มีเวลาสร้าง
- ก่อนการแก้พร้อมกันไม่พบ rate limiter ในโค้ด register/login/orders/track; ปัจจุบันมี limiter เพิ่มเข้ามาแต่ต้องตรวจ trusted proxy และ bootstrap ตามอัปเดตด้านบน ยังไม่ได้ทำ brute force/load test
- Node async route ไม่มี error handling ครอบ database failures; ต้องทดสอบการจัดการ DB ล่ม/ข้อมูลผิดรูปแบบใน instance แยก
- dev frontend ไม่ส่ง CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy; ต้องตรวจ response ของ production hosting อีกครั้ง ไม่สรุปว่า static deployment จะมี headers เหมือน dev
- มี fallback API ไป Hostinger แต่ dev module ยืนยันว่าใช้ local WordPress; ไม่ได้โจมตี Hostinger ในรอบนี้

## ทำซ้ำ

จาก root ของ repository:

```powershell
node security/reproduce-node.mjs
php security/reproduce-wordpress.php --baseline
node security/probe-local.mjs
npm.cmd audit --json
```

Node harness ใช้ Express routers จริงผ่าน HTTP บน loopback ชั่วคราว แต่แทน database pool ด้วยข้อมูลใน memory แล้วปิด listener เมื่อจบ PHP harness โหลด callback จาก plugin จริง แต่แทน WordPress functions ด้วย fixtures จึงไม่ใช่การทดสอบ WordPress/MySQL ครบทั้งระบบ

PHP harness โหลด orders plugin เดี่ยวเพื่อทดสอบ business logic จึงไม่ครอบคลุม fatal error จากการโหลด auth และ orders พร้อมกัน `--baseline` อ่านโค้ดก่อนการแก้จาก Git object ที่ระบุไว้และไม่แก้ไฟล์แอป หากไม่ใส่ flag จะทดสอบ working tree ปัจจุบันและอาจจบ nonzero เมื่อพฤติกรรมช่องโหว่เปลี่ยนไป; นั่นไม่ใช่การรับรองว่าการแก้ผ่านแล้ว Transient fixtures ไม่จำลอง expiration/atomicity ของ limiter หรือ WordPress date filtering อย่างครบถ้วน

ชุด reproduce สองตัวจบ exit 0 เมื่อยืนยันพฤติกรรมช่องโหว่ตามรายงานได้ **exit 0 ไม่ได้แปลว่าระบบปลอดภัย** มี negative controls ตรวจข้อมูลจำเป็นที่ขาดและเบอร์ผิดรูปแบบถูกปฏิเสธด้วย

การทดสอบ live เป็น GET/OPTIONS เท่านั้น ไม่สร้างบัญชี/ออเดอร์จริง ไม่เปลี่ยน paid status จริง ไม่แก้ฐานข้อมูล และไม่ยิงโหลดสูง ไม่มี browser automation สำหรับตรวจ DOM/XSS/session แบบ end-to-end ในเซสชันนี้

## ลำดับก่อนเปิดใช้งาน

1. แก้ fatal error จาก function ซ้ำให้ WordPress boot ได้ แล้วปิดทางเข้าถึง phpMyAdmin แบบ auto-login จากเครือข่าย และใช้สิทธิ์ฐานข้อมูลให้น้อยที่สุด
2. แก้สิทธิ์และความเป็นส่วนตัวของออเดอร์ WordPress รวมถึง logout
3. ถ้าจะเปิด Node service ให้แก้ payment verification, pricing และ ownership ก่อน
4. อัปเดต dependencies พร้อมทดสอบความเข้ากันได้ แล้วใช้ production build แทน dev server
5. ทดสอบ staging ด้วยบัญชีสองคน ยืนยันว่าดู/เปลี่ยนออเดอร์ข้ามบัญชีไม่ได้ และทดสอบการชำระเงินผ่าน sandbox ของผู้ให้บริการ
