# การแก้ความปลอดภัย — 10 กันยายน 2026

สถานะล่าสุดหลังแก้: WordPress/API และหน้าเว็บ local กลับมาทำงาน ชุด regression และ live HTTP checks ผ่าน และ `npm audit --json` รายงาน 0 vulnerabilities ณ เวลาตรวจ

## แก้และยืนยันแล้ว

- รวม helper ที่ `nexus-security.php` และโหลดด้วย `require_once` แก้ fatal function ซ้ำ
- สร้างออเดอร์และติดตามต้องใช้ bearer token ที่ตรวจฝั่ง API; ออเดอร์ใหม่ผูก `customer_user_id` และ query ทุกครั้งกรองเจ้าของ
- เก็บราคาโดยอ่านจาก product meta ฝั่ง server, ปฏิเสธ draft/ราคาไม่พร้อมขาย, normalize เบอร์ก่อนบันทึก และสุ่มรหัสออเดอร์ด้วย 128-bit random
- ยกเลิกการคืนเบอร์ใน tracking และ feed สาธารณะ
- limiter ใช้ REMOTE_ADDR พร้อม MySQL advisory lock เพื่อ serialize การเพิ่ม counter และ fail closed เมื่อ lock ไม่พร้อม; ไม่ลบ authoritative transient ใน persistent cache
- ปิดการ rewrite IP จาก X-Forwarded-For ใน Apache ของ Docker ด้วย `docker/apache/remoteip.conf` แล้ว recreate เฉพาะ WordPress โดยเก็บ volumes เดิม
- token ใหม่เก็บ digest SHA-256 ในฐานข้อมูล ตรวจอายุ/เวลาสร้าง และ revoke เมื่อ logout, reset password, เปลี่ยน profile หรือ role
- หน้าเว็บส่ง Authorization ใน checkout/tracking และเปิดหน้าล็อกอินเมื่อ token ไม่ผ่าน
- CORS ใช้ origin allowlist, รองรับ header ของ logout, ถอด default REST filter ที่สะท้อน origin และใส่ `no-store, private` ให้ API ของ Nexus
- phpMyAdmin เปลี่ยนเป็น cookie/password login ด้วย config ที่ track ใน Git; recreate แล้วตรวจว่า bind เฉพาะ `127.0.0.1:8082` และผู้ไม่มี cookie เห็น login form
- Node `/orders` และ `/webhooks` ปิดด้วย 503 ก่อนแตะฐานข้อมูล ช่องทางเดิมเป็น stub ที่ไม่มี payment verification/authorization; checkout หลักยังใช้ WordPress
- HTML บทความผ่าน sanitizer ก่อน `set:html` เพื่อถอด script/event handler/URL ที่รันโค้ดได้ โดยคง markup บทความทั่วไป
- อัปเดต Astro เป็น 7.3.x, Express 5.2.x และ qs 6.16.0; ถอด Node adapter ที่ไม่ได้ใช้และ integration Tailwind เก่า คง Tailwind 3 ผ่าน PostCSS
- เพิ่ม security headers ให้ Astro dev/preview และเริ่ม dev server ใหม่บน loopback ด้วย dependency ที่อัปเดต

## หลักฐานตรวจ

```powershell
npm.cmd run test:security
node security/test-live.mjs --rate-limit
node security/probe-local.mjs
npm.cmd audit --json
npm.cmd run build:web
```

- PHP regression: 23 checks ผ่าน รวมเจ้าของสองบัญชี, legacy privacy, token expiry/login digest/reset/profile revocation, limiter และ fail-closed lock
- bootstrap รวม mu-plugins ผ่าน
- Node: เส้นทางอันตราย 4 กรณีคืน 503
- HTML sanitizer: คงเนื้อหาปกติและลบ executable markup
- HTTP จริง: WordPress 200; anonymous checkout/tracking 401; CORS origin ที่อนุญาตผ่านและ origin อื่นไม่ถูกสะท้อน; API ไม่ถูก cache; phpMyAdmin ต้องล็อกอิน
- ส่ง failed login 12 คำขอพร้อมกัน โดยแต่ละคำขอปลอม CF/XFF IP ต่างกัน: ได้ 401 จำนวน 5 และ 429 จำนวน 7 แสดงว่า Apache/limiter ไม่ยอมให้ header นี้ข้ามข้อจำกัด
- Astro สร้าง static pages 7 หน้าได้; localhost:4321 ตอบ 200 พร้อม headers ใหม่
- npm audit: info/low/moderate/high/critical เท่ากับ 0 ทุกระดับ

PHP tests ใช้ actual callbacks กับ WordPress/database fixtures; ไม่ใช่การทดสอบ UI ด้วยสองบัญชีจริง ตัว limiter ยังทดสอบ concurrency ผ่าน HTTP กับ WordPress/MySQL ที่รันจริงด้วย ไม่มีการสร้างผู้ใช้หรือออเดอร์จริงในรอบแก้

## ผลที่ผู้ใช้งานจะเห็นและการปล่อย

1. **ผู้ใช้เดิมต้องล็อกอินใหม่หนึ่งครั้ง** เพราะ token เดิมที่เก็บ plaintext ไม่เข้ากับ digest lookup ใหม่ หน้าเว็บจัดการ 401 โดยกลับสู่หน้าล็อกอิน
2. **ออเดอร์เก่าที่ไม่มี customer_user_id จะค้นผ่านหน้าเว็บไม่ได้** เพื่อไม่ให้การอ้างเบอร์โทรเปิดข้อมูลคนอื่น แอดมินยังดูได้ใน WordPress; หากต้องนำประวัติกลับมา ให้ตรวจสอบเจ้าของแล้วผูก user ID ผ่านหน้าจัดการ ห้ามจับคู่จากเบอร์ที่ผู้สมัครกรอกเองโดยไม่ยืนยัน
3. **ยังไม่เปิดรับการยืนยันเงินผ่าน webhook** endpoint ตอบ 503 จนกว่าจะมี integration ตรวจผู้ส่ง ลายเซ็น รายการโอน ยอด ผู้รับ และป้องกัน replay จริง ไม่ถือว่า payment integration เสร็จแล้ว
4. ใช้ production build สำหรับปล่อยเว็บ ไม่เปิด Astro dev server สาธารณะ Dev server ปัจจุบัน bind loopback เพื่อใช้พัฒนาต่อ
5. config `server.headers` ของ Astro ไม่ได้สร้าง headers บน static hosting ให้อัตโนมัติ ตั้ง X-Content-Type-Options, X-Frame-Options/CSP และ Referrer-Policy ที่ hosting ด้วย
6. หาก deploy WordPress หลัง CDN/reverse proxy ให้กำหนดเฉพาะ trusted proxy IP และปิดการเข้าถึง origin ที่ข้าม proxy ก่อนเปิด forwarded-IP rewriting ไม่เชื่อ private IP ranges ทั้งหมดเหมือนค่าตั้งต้นเดิม
7. Docker ของเครื่องนี้ใช้การตั้งค่าใหม่แล้ว; deployment ภายนอกต้องนำ source/config ที่แก้ไปใช้ด้วย ไม่ได้ deploy หรือโจมตี Hostinger จากงานนี้
8. `npm audit=0` เป็นผลของ advisory database ณ เวลาตรวจ ไม่ใช่การรับประกันว่าไม่มีช่องโหว่ทุกชนิด

สคริปต์ `reproduce-node.mjs` และ `reproduce-wordpress.php --retest` เดิมเป็นหลักฐานพฤติกรรมก่อนแก้และตั้งใจคาดว่าช่องโหว่ยังอยู่ จึงอาจ fail หลังแก้ ให้ใช้ `test:security` สำหรับ regression ปัจจุบัน หรือ `reproduce-wordpress.php --baseline` เพื่อดู baseline เดิม

ตรวจอิสระตามสกิล code review แล้ว แก้ทั้ง password-change revocation, concurrent limiter, และข้อควรระวัง persistent cache ที่ผู้ตรวจพบ
