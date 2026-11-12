1. About the Opencart Translate Expert service
See the project repository on GitHub:
https://github.com/vhoshko/opencart-translate-expert

2. Installation, update and configuration of the module:
See the README on GitHub:
https://github.com/vhoshko/opencart-translate-expert#readme

3. How to use the module:
See the README on GitHub:
https://github.com/vhoshko/opencart-translate-expert#readme

4. Support:
Open an issue on GitHub:
https://github.com/vhoshko/opencart-translate-expert/issues

5. Cron translation:
Set the Cron secret in the module settings, save, and call the Cron link from the Website content translation tab in cron. Details:
https://github.com/vhoshko/opencart-translate-expert#cron-translation
A run stops after 15 minutes at most. The host PHP max_execution_time and the scheduler timeout (for example the wget read timeout) still apply, so choose a bulk amount that finishes in time.
