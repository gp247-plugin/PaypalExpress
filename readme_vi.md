# Plugin PaypalExpress

> 🌐 [English](readme.md) · **Tiếng Việt**

Nhận thanh toán **PayPal** cho cửa hàng GP247/Shop của bạn. Khách hàng trả tiền đơn hàng trực tiếp bằng tài khoản PayPal — nhanh, an toàn, và không phải rời khỏi website.

---

## Nhìn nhanh

| | |
| --- | --- |
| **Plugin** | PaypalExpress |
| **Phiên bản** | 3.2.0 |
| **Nhà phát triển** | GP247 |
| **Yêu cầu** | GP247 Core **3.1+** (cấu hình theo cửa hàng, mã hoá bí mật at-rest, yêu cầu thanh toán) · package `gp247/shop` |

## Plugin làm được gì

- 💳 **Thanh toán bằng PayPal** — khách trả tiền bằng tài khoản PayPal ngay trên website.
- 🧪 **Sandbox & Live** — chuyển giữa môi trường thử nghiệm và thực tế của PayPal chỉ bằng một công tắc.
- 🔔 **Webhook** — trạng thái đơn hàng tự cập nhật theo thông báo từ PayPal.
- 🔒 **An toàn** — xác thực chữ ký webhook của PayPal, và client secret của bạn được **mã hoá at-rest**.
- 💱 **Đa tiền tệ** — hoạt động với hệ tiền tệ của GP247/Shop (chỉ cần kiểm tra PayPal có hỗ trợ loại tiền đó không).

---

## Cài đặt

Chọn cách nào tiện hơn với bạn.

**Từ trang quản trị**
1. Đăng nhập trang quản trị GP247.
2. Vào **Extensions / Plugins**.
3. Tìm **PaypalExpress** rồi nhấn **Install**.
4. Làm theo hướng dẫn trên màn hình.

**Từ file ZIP**
1. Tải file ZIP của plugin từ nguồn chính thức.
2. Trong trang quản trị, vào **Extensions / Plugins → Import / Upload**.
3. Chọn file ZIP và tải lên.

Sau đó **kích hoạt** plugin trong phần quản lý plugin.

### Cài bằng dòng lệnh (CLI, gp247 3.x)

Từ gp247 3.x, bạn có thể tải **PaypalExpress** từ thư viện GP247 và cài ngay bằng dòng lệnh mà không cần mở admin. Mở Terminal tại thư mục gốc website rồi chạy:

```bash
# 1) Chỉ làm 1 lần cho mỗi website: đăng ký API License (miễn phí) để kết nối thư viện GP247
php artisan gp247:ext-register-license

# 2) Tải plugin từ thư viện và cài
php artisan gp247:ext-install --type=plugin --key=PaypalExpress
```

- Trước bước 1, kiểm tra `APP_URL` trong `.env` là **domain thật** của website (không để `http://localhost`), vì license được gắn với domain này.
- Cài xong, plugin được **bật sẵn** và cache tự làm mới, bạn không cần thao tác gì thêm trong admin.
- Lệnh tự kiểm tra điều kiện khai báo trong `gp247.json` (phiên bản core, gói composer, plugin phụ thuộc). Nếu thiếu, lệnh dừng lại và báo rõ thiếu gì.
- Plugin này cần gói `gp247/shop` đã được cài; nếu thiếu, lệnh sẽ dừng lại và báo.
- Nếu thư mục `app/GP247/Plugins/PaypalExpress` đã có sẵn trên máy (chép thủ công hoặc có sẵn theo bộ cài), lệnh sẽ **cài tại chỗ**, không tải lại.
- Nếu plugin đã được cài, lệnh sẽ từ chối. Để lên bản mới, chạy `php artisan gp247:ext-update --type=plugin --key=PaypalExpress`.
- Thêm `--json` vào cuối lệnh để nhận kết quả dạng máy đọc được (dùng cho script/CI).
- Phần **Cấu hình** bên dưới vẫn phải làm sau khi cài: plugin đã bật nhưng chỉ nhận thanh toán được khi bạn nhập Chế độ Sandbox, Client ID / Secret và Webhook ID trong trang quản trị.
- Chi tiết: [Hướng dẫn cài đặt Plugin & Template](https://github.com/gp247net/gp247-docs/blob/main/extension/install-extension_vi.md) · [Tra cứu lệnh](https://github.com/gp247net/gp247-docs/blob/main/system/command-line-reference_vi.md).

---

## Cấu hình

Từ **phiên bản 3.1**, mọi thứ được cấu hình trong trang quản trị — không cần sửa `.env`.

Mở **Admin → Plugins → Paypal Express** và điền các ô sau (**theo từng cửa hàng**):

| Ô | Nhập gì |
| --- | --- |
| **Chế độ Sandbox** | Bật = môi trường thử nghiệm · Tắt = thực tế (live) |
| **Client ID / Secret (Sandbox)** | Thông tin xác thực môi trường thử nghiệm |
| **Client ID / Secret (Live)** | Thông tin xác thực môi trường thực tế |
| **Webhook ID** | ID webhook lấy từ tài khoản PayPal Developer (xem [Webhook](#webhook) bên dưới) |

Vài điều nên biết:

- 🔒 **Client secret được mã hoá at-rest** (`enc:v2:…`) — không bao giờ lưu ở dạng thô.
- 🏬 **Theo từng cửa hàng** — site nhiều cửa hàng: chủ site đặt tài khoản PayPal riêng cho từng store; sàn thương mại: chủ sàn đặt một tài khoản, các store kế thừa. Chỉ chủ site/chủ sàn mở được màn này.
- 🔗 **URL chuyển hướng tự động** — plugin tự sinh theo tên miền của cửa hàng, nên không có gì để cấu hình.

> **Đang nâng cấp từ bản cũ dùng `.env`?** Các bản trước lưu thông tin trong `.env`. Khi nâng cấp lên 3.1, plugin **tự nhập một lần** vào cơ sở dữ liệu (bí mật được mã hoá), rồi từ đó chỉ đọc cơ sở dữ liệu — `.env` không còn dùng lúc chạy. File `.env` của bạn không bị đụng tới và có thể xoá đi sau khi đã nhập.
>
> *Nếu site chạy `php artisan config:cache`, bước tự nhập sẽ bị bỏ qua — chỉ cần nhập lại các giá trị trong màn quản trị.*

**Các biến `.env` cũ** (chỉ dùng cho lần di trú một lần nói trên):

```
PAYPAL_SANDBOX=true
PAYPAL_CLIENT_ID_SANDBOX=your_sandbox_client_id
PAYPAL_CLIENT_SECRET_SANDBOX=your_sandbox_client_secret
PAYPAL_CLIENT_ID_LIVE=your_live_client_id
PAYPAL_CLIENT_SECRET_LIVE=your_live_client_secret
PAYPAL_WEBHOOK_ID=your_webhook_id
```

---

## Hỗ trợ tiền tệ

GP247 hỗ trợ nhiều loại tiền tệ, nhưng **PayPal chỉ chấp nhận một số loại**:

- Kiểm tra loại tiền của bạn trong danh sách [PayPal Supported Currencies](https://developer.paypal.com/reference/currency-codes) trước.
- Nếu khách cố thanh toán bằng loại tiền không được hỗ trợ, họ sẽ thấy thông báo lỗi.
- An toàn nhất: **USD, EUR, GBP, CAD, AUD**.

---

## Một giao dịch diễn ra thế nào

1. Khách thêm sản phẩm vào giỏ và tiến hành thanh toán.
2. GP247 tạo đơn hàng và đưa khách sang PayPal.
3. Khách đăng nhập PayPal và xác nhận.
4. PayPal đưa khách quay lại website của bạn.
5. GP247 xác thực giao dịch và cập nhật trạng thái đơn.
6. Khách thấy xác nhận thanh toán.

---

## Link thanh toán (Yêu cầu thanh toán)

Từ **3.2.0**, với `gp247/shop` có màn **Yêu cầu thanh toán**, PayPal còn thu được các khoản **ngoài giỏ hàng** (trả nốt đơn, công nợ, đặt cọc…):

1. Admin tạo yêu cầu thu, bấm **Tạo link thanh toán** và gửi link cho khách.
2. Khách mở link, chọn **PayPal**, xác nhận trên PayPal; khi quay về, tiền được thu và ghi vào yêu cầu **một lần**.
3. Hoàn tiền: trên dòng tiền đã thu, bấm **Hoàn qua cổng** (cần quyền chi tiền). Hoàn làm trên PayPal cũng được ghi về yêu cầu qua webhook.

PayPal chỉ hiện trên trang link khi **cửa hàng sở hữu yêu cầu** đã có Client ID / Secret. Nên đăng ký thêm sự kiện webhook `PAYMENT.CAPTURE.COMPLETED` để khoản đã thu vẫn được ghi nếu khách đóng trình duyệt ngay lúc quay về.

## Webhook

Plugin lắng nghe thông báo từ PayPal tại:

```
https://your-domain.com/plugin/paypal-express/webhook
```

Đăng ký URL này trong tài khoản **PayPal Developer**, rồi dán **Webhook ID** vào màn cấu hình plugin trong trang quản trị (theo từng cửa hàng).

---

## Changelog

### Version 3.2.0
- PayPal thu và hoàn tiền cho **Yêu cầu thanh toán** của `gp247/shop` (link thanh toán `/pay/…`), dùng tài khoản PayPal của cửa hàng sở hữu yêu cầu; nhận thêm sự kiện `PAYMENT.CAPTURE.COMPLETED` cho các khoản này. Luồng thanh toán đơn hàng không đổi; shop chưa có tính năng này thì plugin chạy như 3.1.3. Yêu cầu GP247 Core 3.1+.

### Version 3.1.3
- Màn cấu hình: các ô được gom thành khối Chế độ / Sandbox / Live / Webhook / Trạng thái đơn, có nhãn "Đang dùng" ở môi trường đang bật (cần GP247 Core hỗ trợ khối cấu hình; core cũ hiện danh sách phẳng), mỗi ô có chú thích ngắn (lấy Client ID/Secret và Webhook ID ở đâu, đăng ký sự kiện nào, từng trạng thái dùng để làm gì), và các ô giữ thứ tự cố định trên mọi máy chủ (trước đây phụ thuộc cơ sở dữ liệu).

### Version 3.1.2
- **Sửa lỗi: webhook PayPal chưa bao giờ tới được plugin** ở bản 3.1.x — endpoint nằm sau lớp CSRF/bảo trì của storefront nên PayPal luôn nhận 419 và hoàn tiền không được ghi vào đơn. Webhook nay là endpoint riêng, có giới hạn tần suất. **URL không đổi**, Webhook ID đã đăng ký vẫn dùng được. PayPal tự gửi lại sự kiện thất bại trong vài ngày; hoàn tiền cũ hơn cần đối chiếu tay trên PayPal dashboard.
- Sự kiện hoàn tiền nay đặt đúng "Trạng thái đơn khi hoàn tiền" đã cấu hình (trước đây sai khoá nên trạng thái bị trống) và đi qua luồng đổi trạng thái chuẩn của shop, nên lịch sử đơn, sự kiện và tồn kho nhất quán. Hoàn tiền trên đơn đã huỷ chỉ ghi tiền.
- Trang capture không còn hiện trang trắng khi PayPal chưa hoàn tất; đơn đã thanh toán hoặc đã đóng không bao giờ bị capture/huỷ lại từ link quay về/huỷ.
- Số tiền gửi PayPal lấy từ đơn đã lưu (không lấy từ session).
- Log không còn chứa session của khách hay toàn bộ nội dung webhook.
- Gỡ plugin xoá cấu hình của mọi cửa hàng (kể cả bí mật đã mã hoá).

### Version 3.1
- Thông tin kết nối PayPal (client id/secret sandbox+live, webhook id, chế độ sandbox) chuyển từ `.env` vào màn cấu hình admin, **theo từng cửa hàng**, client secret được **mã hoá at-rest** (`enc:v2:…`). `storeScope: store` — root admin đặt tài khoản PayPal riêng cho từng store; plugin không đăng ký `store_scoped_segments` nên chỉ chủ site/chủ sàn (không bao giờ là vendor) mở được màn này.
- **Cơ sở dữ liệu là nguồn cấu hình duy nhất lúc chạy — ngừng đọc `.env`.** Site cũ còn `.env` được **tự nhập một lần** khi nâng cấp (client secret mã hoá) rồi từ đó chỉ dùng cơ sở dữ liệu. `return_url`/`cancel_url` không còn cấu hình tay, tự sinh theo route + tên miền cửa hàng.
- Yêu cầu GP247 Core 3.0.3+.

### Version 2.0
- Xây dựng lại màn hình cấu hình admin bằng TailAdmin/Livewire (yêu cầu GP247 Core 2.0); trạng thái đơn hàng/thanh toán cho sự kiện thành công và hoàn tiền giờ được chọn qua dropdown, vẫn lưu vào đúng các dòng `admin_config` như trước nên giá trị đã cấu hình được giữ nguyên khi nâng cấp.
- Sửa lỗi có sẵn từ trước: mục "Paypal Express" trong nhóm Payment method ở sidebar admin có thể bị nhân đôi khi cài đặt và không bao giờ bị xóa khi gỡ cài đặt (do sai URI menu).

### Version 1.0.0
- Phát hành lần đầu.

---

<sub>Được phát triển bởi **GP247** · phân phối theo giấy phép tương ứng.</sub>
