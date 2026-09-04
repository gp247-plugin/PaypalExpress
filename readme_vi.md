# Plugin PaypalExpress

## Tổng quan

PaypalExpress là một plugin cung cấp tính năng thanh toán thông qua PayPal dành cho GP247/Shop. Plugin này cho phép khách hàng thanh toán đơn hàng trực tiếp bằng tài khoản PayPal của họ, mang lại trải nghiệm thanh toán nhanh chóng và an toàn.

## Thông tin cơ bản

- **Tên plugin**: PaypalExpress
- **Phiên bản**: 3.1
- **Nhà phát triển**: GP247
- **Email hỗ trợ**: support@gp247.net
- **Liên kết**: https://github.com/gp247net/PaypalExpress
- **Yêu cầu hệ thống**: 
  - Core GP247 phiên bản 3.0.3 trở lên (cần cấu hình theo store + mã hoá bí mật at-rest)
  - Package gp247/shop

## Tính năng chính

1. **Thanh toán trực tiếp qua PayPal**: Cho phép khách hàng thanh toán đơn hàng bằng tài khoản PayPal mà không cần rời khỏi trang web.

2. **Hỗ trợ môi trường Sandbox và Live**: Có thể cấu hình để sử dụng môi trường thử nghiệm (Sandbox) hoặc môi trường thực tế (Live) của PayPal.

3. **Xử lý webhook**: Tự động cập nhật trạng thái đơn hàng dựa trên thông báo từ PayPal thông qua webhook.

4. **Bảo mật cao**: Tích hợp xác thực chữ ký webhook của PayPal để đảm bảo tính bảo mật của các giao dịch.

5. **Hỗ trợ nhiều loại tiền tệ**: Tích hợp với hệ thống tiền tệ của GP247/Shop. Tuy nhiên, bạn cần kiểm tra xem loại tiền tệ bạn sử dụng có được hỗ trợ bởi PayPal hay không.

## Cài đặt và cấu hình

### Cài đặt

Có hai cách để cài đặt plugin PaypalExpress:

#### Phương pháp 1: Cài đặt tự động thông qua Admin Panel
1. Đăng nhập vào trang quản trị GP247.
2. Điều hướng đến phần "Extensions" hoặc "Plugins".
3. Tìm "PaypalExpress" trong danh sách các plugin có sẵn.
4. Nhấp vào nút "Install" bên cạnh nó.
5. Làm theo hướng dẫn trên màn hình để hoàn tất cài đặt.

#### Phương pháp 2: Cài đặt thủ công thông qua file ZIP
1. Tải xuống file ZIP của plugin PaypalExpress từ nguồn chính thức.
2. Đăng nhập vào trang quản trị GP247.
3. Điều hướng đến phần "Extensions" hoặc "Plugins".
4. Nhấp vào nút "Import" hoặc "Upload".
5. Chọn file ZIP đã tải xuống và nhấp vào "Upload" hoặc "Import".
6. Làm theo hướng dẫn trên màn hình để hoàn tất cài đặt.

Sau khi cài đặt, kích hoạt plugin trong phần quản lý plugin.

### Cấu hình

**Từ phiên bản 3.1**, thông tin kết nối PayPal được cấu hình **hoàn toàn trong** **Admin -> Plugins -> Paypal Express**, **theo từng cửa hàng**, và các client secret được **mã hoá at-rest** (`enc:v2:...`). **Cơ sở dữ liệu là nguồn cấu hình duy nhất lúc chạy — không còn đọc `.env`.** Site nhiều cửa hàng: chủ site đặt tài khoản PayPal riêng cho từng store; sàn thương mại: chủ sàn đặt một tài khoản, các store kế thừa. Chỉ chủ site/chủ sàn mở được màn này (store-admin/vendor bị chặn). Các ô: Chế độ Sandbox, Client ID/Secret (Sandbox), Client ID/Secret (Live), Webhook ID.

URL chuyển hướng sau thanh toán (`return_url`) và khi huỷ (`cancel_url`) **không cần cấu hình** — plugin tự sinh theo route và tên miền của cửa hàng đang thanh toán.

> **Legacy `.env` (đã ngừng dùng lúc chạy):** các biến `PAYPAL_*` dưới đây **không còn được đọc khi vận hành**. Khi nâng cấp lên 3.1, nếu site cũ còn đặt chúng trong `.env`, plugin **tự nhập một lần** vào cơ sở dữ liệu (client secret được mã hoá) để không mất cấu hình, rồi từ đó chỉ dùng cơ sở dữ liệu. `.env` không bị xoá nhưng có thể bỏ đi sau khi đã nhập. (Site chạy `php artisan config:cache` sẽ không tự nhập được — hãy nhập lại trực tiếp trong màn admin.)

```
# Legacy — chỉ để di trú một lần khi nâng cấp, không dùng lúc chạy
PAYPAL_SANDBOX=true
PAYPAL_CLIENT_ID_SANDBOX=your_sandbox_client_id
PAYPAL_CLIENT_SECRET_SANDBOX=your_sandbox_client_secret
PAYPAL_CLIENT_ID_LIVE=your_live_client_id
PAYPAL_CLIENT_SECRET_LIVE=your_live_client_secret
PAYPAL_WEBHOOK_ID=your_webhook_id
```

### Hỗ trợ tiền tệ

Mặc dù GP247 hỗ trợ đa tiền tệ, PayPal có các yêu cầu cụ thể về tiền tệ:

- PayPal chỉ hỗ trợ một số loại tiền tệ nhất định cho các giao dịch.
- Trước khi sử dụng một loại tiền tệ cụ thể, hãy kiểm tra xem nó có được PayPal hỗ trợ hay không bằng cách tham khảo tài liệu [PayPal Supported Currencies](https://developer.paypal.com/docs/api/reference/currency-codes/).
- Nếu bạn cố gắng xử lý thanh toán với một loại tiền tệ không được hỗ trợ, plugin sẽ hiển thị thông báo lỗi cho khách hàng.
- Để tương thích tối ưu, hãy cân nhắc sử dụng các loại tiền tệ chính như USD, EUR, GBP, CAD hoặc AUD.

## Quy trình thanh toán

1. Khách hàng thêm sản phẩm vào giỏ hàng và tiến hành thanh toán.
2. Hệ thống tạo đơn hàng và chuyển hướng khách hàng đến trang thanh toán của PayPal.
3. Khách hàng đăng nhập vào tài khoản PayPal và xác nhận thanh toán.
4. PayPal chuyển hướng khách hàng về trang web của bạn.
5. Hệ thống xác thực giao dịch và cập nhật trạng thái đơn hàng.
6. Khách hàng nhận được xác nhận thanh toán.

## Xử lý webhook

Plugin tích hợp xử lý webhook từ PayPal để tự động cập nhật trạng thái đơn hàng. Webhook sẽ được gửi đến URL:

```
https://your-domain.com/plugin/paypal-express/webhook
```

Bạn cần đăng ký webhook này trong tài khoản PayPal Developer và nhập **Webhook ID** vào màn cấu hình plugin trong admin (theo cửa hàng).

## Hỗ trợ và liên hệ

Nếu bạn cần hỗ trợ hoặc có câu hỏi về plugin PaypalExpress, vui lòng liên hệ:

- Email: support@gp247.net
- GitHub: https://github.com/gp247net/PaypalExpress

## Giấy phép

Plugin PaypalExpress được phát triển bởi GP247 và được phân phối theo giấy phép tương ứng. 

## Changelog

### Version 3.1
- Thông tin kết nối PayPal (client id/secret sandbox+live, webhook id, chế độ sandbox) chuyển từ `.env` vào màn cấu hình admin, **theo từng cửa hàng**, client secret được **mã hoá at-rest** (`enc:v2:...`). `storeScope: platform` — chỉ chủ site/chủ sàn cấu hình; root admin đặt tài khoản PayPal riêng cho từng store.
- **Cơ sở dữ liệu là nguồn cấu hình duy nhất lúc chạy — ngừng đọc `.env`.** Site cũ còn `.env` được **tự nhập một lần** khi nâng cấp (client secret mã hoá) rồi từ đó chỉ dùng cơ sở dữ liệu. `return_url`/`cancel_url` không còn cấu hình tay, tự sinh theo route + tên miền cửa hàng.
- Yêu cầu GP247 Core 3.0.3+.

### Version 2.0
- Xây dựng lại màn hình cấu hình admin bằng TailAdmin/Livewire (yêu cầu GP247 Core 2.0); trạng thái đơn hàng/thanh toán cho sự kiện thành công và hoàn tiền giờ được chọn qua dropdown, vẫn lưu vào đúng các dòng `admin_config` như trước nên giá trị đã cấu hình được giữ nguyên khi nâng cấp
- Sửa lỗi có sẵn từ trước: mục "Paypal Express" trong nhóm Payment method ở sidebar admin có thể bị nhân đôi khi cài đặt và không bao giờ bị xóa khi gỡ cài đặt (do sai URI menu)

### Version 1.0.0
- Phát hành lần đầu