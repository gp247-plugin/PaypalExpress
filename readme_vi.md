# Plugin PaypalExpress

> 🌐 [English](readme.md) · **Tiếng Việt**

Nhận thanh toán **PayPal** cho cửa hàng GP247/Shop của bạn. Khách hàng trả tiền đơn hàng trực tiếp bằng tài khoản PayPal — nhanh, an toàn, và không phải rời khỏi website.

---

## Nhìn nhanh

| | |
| --- | --- |
| **Plugin** | PaypalExpress |
| **Phiên bản** | 3.1 |
| **Nhà phát triển** | GP247 |
| **Yêu cầu** | GP247 Core **3.0.3+** (cấu hình theo cửa hàng + mã hoá bí mật at-rest) · package `gp247/shop` |

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

- Kiểm tra loại tiền của bạn trong danh sách [PayPal Supported Currencies](https://developer.paypal.com/docs/api/reference/currency-codes/) trước.
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

## Webhook

Plugin lắng nghe thông báo từ PayPal tại:

```
https://your-domain.com/plugin/paypal-express/webhook
```

Đăng ký URL này trong tài khoản **PayPal Developer**, rồi dán **Webhook ID** vào màn cấu hình plugin trong trang quản trị (theo từng cửa hàng).

---

## Changelog

### Version 3.1
- Thông tin kết nối PayPal (client id/secret sandbox+live, webhook id, chế độ sandbox) chuyển từ `.env` vào màn cấu hình admin, **theo từng cửa hàng**, client secret được **mã hoá at-rest** (`enc:v2:…`). `storeScope: platform` — chỉ chủ site/chủ sàn cấu hình; root admin đặt tài khoản PayPal riêng cho từng store.
- **Cơ sở dữ liệu là nguồn cấu hình duy nhất lúc chạy — ngừng đọc `.env`.** Site cũ còn `.env` được **tự nhập một lần** khi nâng cấp (client secret mã hoá) rồi từ đó chỉ dùng cơ sở dữ liệu. `return_url`/`cancel_url` không còn cấu hình tay, tự sinh theo route + tên miền cửa hàng.
- Yêu cầu GP247 Core 3.0.3+.

### Version 2.0
- Xây dựng lại màn hình cấu hình admin bằng TailAdmin/Livewire (yêu cầu GP247 Core 2.0); trạng thái đơn hàng/thanh toán cho sự kiện thành công và hoàn tiền giờ được chọn qua dropdown, vẫn lưu vào đúng các dòng `admin_config` như trước nên giá trị đã cấu hình được giữ nguyên khi nâng cấp.
- Sửa lỗi có sẵn từ trước: mục "Paypal Express" trong nhóm Payment method ở sidebar admin có thể bị nhân đôi khi cài đặt và không bao giờ bị xóa khi gỡ cài đặt (do sai URI menu).

### Version 1.0.0
- Phát hành lần đầu.

---

<sub>Được phát triển bởi **GP247** · phân phối theo giấy phép tương ứng.</sub>
