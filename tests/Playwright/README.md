## ddev-playwright addon
https://github.com/julienloizelet/ddev-playwright

### Install
```
$ ddev restart
$ ddev playwright-install
```

### Usage
https://github.com/julienloizelet/ddev-playwright#basic-usage
```
$ ddev playwright test
```

### Known issues
It does not open browser in macOS, to make it work use `npx`:
```
$ cd tests/Playwright
$ npx playwright test
```

