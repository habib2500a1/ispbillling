# Default company branding (git-tracked)

নতুন server deploy এ `bootstrap-app.sh` এই ফাইলগুলো `storage/app/public/company-branding/`-এ auto copy করে (logo না থাকলে)।

- `company-logo.png` — admin login + panel logo
- `favicon-32.png` — browser favicon

Production এ নতুন logo upload করলে storage-এ যায়; git update করতে চাইলে এখানে replace করে commit করুন।
