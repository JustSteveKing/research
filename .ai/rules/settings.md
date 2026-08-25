---
paths:
  - 'app/{Providers/AppServiceProvider.php,Services/Settings/**}'
---

# Settings

## Credential-backed clients must be bind(), never singleton()
TabstackClient and OllamaClient are built from AppSettings, which reads the settings store, not just config/.env. NativePHP starts one long-lived worker per queue at app boot, so a singleton resolved before the reader entered a key would hold the FakeTabstackClient for the life of that worker and every run would silently replay database/fixtures/research-fast.sse instead of researching. Bind them (and AppSettings, which is unregistered and so already resolves fresh) so each job re-reads the credentials. AppSettings memoises per instance, not per process, for the same reason. tests/Feature/SettingsBindingTest.php fails if either binding goes back to singleton.

Settings resolution order is store first, then config: a checkout keeps working from .env while a packaged app is configured by the person running it. .env therefore wins whenever the store is empty, which is why the settings page surfaces tabstack.from_env.
