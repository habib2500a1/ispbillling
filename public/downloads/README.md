# Mobile APK downloads

APK files are **not** stored in this repository (too large for git).

They are published to **GitHub Releases**:

- Repo: `habib2500a1/ispbillling`
- Radiant ISP app: asset `isp-radiant.apk` (tag e.g. `isp-radiant-v2.6.3`)
- MFS SMS Verify: asset `isp-mfs-verify.apk` (tag e.g. `mfs-verify-v1.0.4`)

## Build & upload

```bash
# Radiant ISP (staff + customer)
./scripts/build-mobile-apk.sh https://bill.flixbd.xyz
./scripts/github-release-apk.sh isp-radiant-v2.6.3 mobile/isp_radiant/build/app/outputs/flutter-apk/app-release.apk

# MFS Verify (payment SIM)
./scripts/build-mfs-verify-apk.sh https://bill.flixbd.xyz
./scripts/github-release-apk.sh mfs-verify-v1.0.4 mobile/mfs_verify/build/app/outputs/flutter-apk/app-release.apk

# Remove old local copies (667MB+)
./scripts/clean-local-apks.sh
```

Requires [GitHub CLI](https://cli.github.com/): `gh auth login`

## Server `.env`

```env
MOBILE_USE_GITHUB_RELEASES=true
MOBILE_GITHUB_REPO=habib2500a1/ispbillling
MOBILE_RADIANT_GITHUB_TAG=isp-radiant-v2.6.3
MOBILE_MFS_GITHUB_TAG=mfs-verify-v1.0.4
```

Optional direct overrides: `MOBILE_APK_URL`, `MOBILE_MFS_VERIFY_APK_URL`
