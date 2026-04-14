# Tài liệu vận hành WebNIC HostBill Modules

## Mục đích

Tài liệu này dành cho đội vận hành, triển khai và kiểm thử nội bộ khi đưa các module WebNIC vào HostBill.

## Các module trong phạm vi

- `webnic_domains`
- `webnic_ssl`
- `webnic_dns`

## Ý nghĩa từng module

### `webnic_domains`

Module quản lý domain registrar qua WebNIC, hỗ trợ:

- đăng ký tên miền
- gia hạn
- transfer vào
- cập nhật nameserver
- quản lý khoá transfer
- quản lý WHOIS privacy
- đồng bộ contact
- gửi lại email xác thực
- tải ownership certificate

### `webnic_ssl`

Module quản lý SSL qua WebNIC, hỗ trợ:

- tạo đơn SSL
- gia hạn
- reissue
- huỷ đơn
- đổi phương thức DCV
- gửi lại email DCV
- đồng bộ trạng thái chứng thư
- tải certificate

### `webnic_dns`

Module quản lý DNS qua WebNIC, hỗ trợ:

- tạo zone
- xoá zone
- quản lý record
- lấy default nameserver
- hiển thị cấu hình DNS trong admin/client

## Cấu trúc triển khai runtime đề xuất

- `webnic_domains` -> `includes/modules/Domain/webnic_domains`
- `webnic_ssl` -> `includes/modules/Hosting/webnic_ssl`
- `webnic_dns` -> `includes/modules/Hosting/webnic_dns`

Mỗi module đã tự nhúng WebNIC API client riêng, không còn phụ thuộc thư mục dùng chung.

## Thông tin cần chuẩn bị trước khi test

### Tài khoản WebNIC

Cần có:

- username API
- password API
- môi trường OTE hoặc live
- registrant user ID cho domain

### HostBill

Cần có:

- quyền admin
- server/product đã tạo đúng loại module
- môi trường staging riêng

### PHP / Server

Cần kiểm tra:

- bật `curl`
- bật `json`
- cho phép outbound HTTPS
- thư mục temp cho PHP có thể ghi được

## Quy trình triển khai khuyến nghị

1. backup source code và database HostBill
2. copy module vào đúng thư mục runtime
3. cấu hình server dùng OTE trước
4. cấu hình product/service cần test
5. test từng module theo checklist
6. chỉ chuyển live sau khi staging pass

## Checklist test cho domain

- module hiển thị trong HostBill
- test credentials thành công
- lookup domain hoạt động
- register domain test thành công
- renew domain test thành công
- đổi nameserver thành công
- lock/unlock thành công
- contact sync thành công
- resend verify email thành công
- download certificate thành công

## Checklist test cho SSL

- product config load được catalog/product key
- tạo order thành công
- lấy được approver email
- đổi DCV thành công
- resend email DCV thành công
- sync trạng thái đơn thành công
- tải certificate sau khi issue thành công

## Checklist test cho DNS

- product config hiển thị đúng
- app summary lấy được nameserver / record types
- tạo zone thành công
- list zone thành công
- thêm record thành công
- sửa record thành công
- xoá record thành công
- client area DNS load được

## Các lỗi thường gặp

### 1. Không load được module

Kiểm tra:

- thư mục runtime đặt đúng chưa
- tên file class chính đúng chưa
- quyền file có đúng không

### 2. Gọi API lỗi xác thực

Kiểm tra:

- username/password đúng chưa
- đang dùng OTE hay live đúng chưa
- firewall outbound có chặn không

### 3. Load được UI nhưng action thất bại

Kiểm tra:

- config product/service còn thiếu field
- dữ liệu WebNIC trả về khác assumption hiện tại
- account test không có quyền với endpoint đó

### 4. SSL không tải được certificate

Kiểm tra:

- order đã issue chưa
- PHP temp có ghi được không
- format download có phù hợp không

### 5. DNS client area không hiện

Kiểm tra:

- module nằm đúng nhánh `Hosting`
- file `user/class.webnic_dns_controller.php` đã được deploy chưa
- HostBill installation có bật đúng DNS type/controller flow không

## Lưu ý quan trọng

- Domain và SSL hiện chưa có custom client controller riêng do chưa xác thực chắc chắn pattern runtime trong reference.
- Một số field response từ WebNIC có thể thay đổi theo TLD, sản phẩm SSL, hoặc loại gói DNS.
- Bắt buộc test trên OTE trước khi dùng live.

## Khuyến nghị vận hành

- lưu riêng bộ credentials OTE và live
- có ít nhất 1 account test cho mỗi module
- lưu lại payload lỗi thực tế khi test staging
- bổ sung runbook nội bộ khi đã xác thực flow production