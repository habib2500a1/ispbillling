# Clients directory CSS

| File | Edit when changing… |
|------|---------------------|
| `01-page-shell.css` | Page background, `.cl-dir` variables |
| `02-chrome-toolbar.css` | Stats, tabs, action bar, search |
| `03-table.css` | Columns, PPP name, actions row, mobile |
| `04-due-page.css` | Due clients list (`?due`) |
| `05-vip-page.css` | VIP list |

Loaded by `App\Support\ClientsDirectoryStyles` on `/admin/subscribers` directory routes.

```bash
./scripts/concat-clients-directory-css.sh
```
