# Laravel Herd Dashboard

## Cách sử dụng

1. Clone hoặc download repo này về máy tính
2. Link thư mục `dashboard` vào Laravel Herd

```bash
git clone https://github.com/Hungnth/laravel-herd-dashboard dashboard
cd dashboard
herd link
```

3. Sửa file `index.php`, các phần nên chỉnh sửa:

    - `herd_sites_path`: Đường dẫn chứa các website của Laravel Herd

    - `phpMyAdmin_url`: Nếu dùng phpMyAdmin
    
    -  `port`: Database port nếu không dùng port mặc định
  
4. Kiểm tra lại Laravel Herd đã nhận chưa và truy cập
