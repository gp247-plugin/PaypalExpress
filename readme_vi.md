# Plugin PaypalExpress

## Tổng quan

PaypalExpress là một plugin cung cấp tính năng thanh toán thông qua PayPal dành cho GP247/Shop. Plugin này cho phép khách hàng thanh toán đơn hàng trực tiếp bằng tài khoản PayPal của họ, mang lại trải nghiệm thanh toán nhanh chóng và an toàn.

## Thông tin cơ bản

- **Tên plugin**: PaypalExpress
- **Phiên bản**: 1.0.0
- **Nhà phát triển**: GP247
- **Email hỗ trợ**: support@gp247.net
- **Liên kết**: https://github.com/gp247net/PaypalExpress
- **Yêu cầu hệ thống**: 
  - Core GP247 phiên bản 1.1 trở lên
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

Để sử dụng plugin, bạn cần cấu hình các thông tin sau trong file `.env`:

```
PAYPAL_SANDBOX=true
PAYPAL_CLIENT_ID_SANDBOX=your_sandbox_client_id
PAYPAL_CLIENT_SECRET_SANDBOX=your_sandbox_client_secret
PAYPAL_CLIENT_ID_LIVE=your_live_client_id
PAYPAL_CLIENT_SECRET_LIVE=your_live_client_secret
PAYPAL_RETURN_URL=https://your-domain.com/plugin/paypal-express/capture-payment
PAYPAL_CANCEL_URL=https://your-domain.com/plugin/paypal-express/cancel-payment
PAYPAL_WEBHOOK_ID=your_webhook_id
```

Trong đó:
- `PAYPAL_SANDBOX`: Đặt `true` để sử dụng môi trường thử nghiệm, `false` để sử dụng môi trường thực tế.
- `PAYPAL_CLIENT_ID_SANDBOX` và `PAYPAL_CLIENT_SECRET_SANDBOX`: Thông tin xác thực cho môi trường thử nghiệm.
- `PAYPAL_CLIENT_ID_LIVE` và `PAYPAL_CLIENT_SECRET_LIVE`: Thông tin xác thực cho môi trường thực tế.
- `PAYPAL_RETURN_URL`: URL mà PayPal sẽ chuyển hướng sau khi thanh toán thành công.
- `PAYPAL_CANCEL_URL`: URL mà PayPal sẽ chuyển hướng nếu khách hàng hủy thanh toán.
- `PAYPAL_WEBHOOK_ID`: ID webhook được tạo trong tài khoản PayPal Developer.

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

Bạn cần đăng ký webhook này trong tài khoản PayPal Developer và cập nhật `PAYPAL_WEBHOOK_ID` trong file `.env`.

## Hỗ trợ và liên hệ

Nếu bạn cần hỗ trợ hoặc có câu hỏi về plugin PaypalExpress, vui lòng liên hệ:

- Email: support@gp247.net
- GitHub: https://github.com/gp247net/PaypalExpress

## Giấy phép

Plugin PaypalExpress được phát triển bởi GP247 và được phân phối theo giấy phép tương ứng. 