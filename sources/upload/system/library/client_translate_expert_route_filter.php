<?php
/**
 * Decides whether Translate Expert must not load on an admin page.
 *
 * Used by the header op in system/OpenCartTranslateExpertClient.ocmod.xml (OC 1.5–3.0)
 * and by isCurrentRouteExcluded() in the admin controller (OC4).
 *
 * Keep this file PHP 5.6 compatible and free of OpenCart dependencies (it is loaded on every admin page).
 */
class ClientTranslateExpertRouteFilter
{
	/**
	 * @param string|null $route        Current request route ($this->request->get['route']), null if absent
	 * @param string      $rulesText    Setting value: one rule per line
	 * @param string      $defaultRoute Route OpenCart opens when the request has no route (common/home on OC 1.5, common/dashboard on 2.0+)
	 * @return bool True if the module must not load on this route
	 */
	public static function isExcluded($route, $rulesText, $defaultRoute)
	{
		$route = self::normalizeRoute((string)$route, $defaultRoute);

		// Never exclude the module page: it needs the header JS.
		if (strpos($route, 'module/client_translate_expert') !== false) {
			return false;
		}

		foreach (explode("\n", (string)$rulesText) as $rule) {
			$rule = self::normalizeRule($rule, $defaultRoute);
			if ($rule === '') {
				continue;
			}

			if (substr($rule, -1) === '*') {
				// A rule of only * matches all routes. Do not call strpos() with an empty needle (warning on PHP < 8).
				$prefix = substr($rule, 0, -1);
				if ($prefix === '' || strpos($route, $prefix) === 0) {
					return true;
				}
			} elseif ($rule === $route) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Default route for the running OpenCart version.
	 *
	 * @return string
	 */
	public static function defaultRoute()
	{
		return (defined('VERSION') && version_compare(VERSION, '2.0', '<')) ? 'common/home' : 'common/dashboard';
	}

	private static function normalizeRoute($route, $defaultRoute)
	{
		$route = trim($route, " \t/");
		if ($route === '') {
			$route = $defaultRoute;
		}

		// OC 4.0.2+ already does this for the request route; old OC 4.0.0/4.0.1 used |.
		return str_replace('|', '.', $route);
	}

	private static function normalizeRule($rule, $defaultRoute)
	{
		$rule = trim($rule);

		// Accept "route=x", "index.php?route=x&user_token=..." and a trailing "/".
		$pos = strpos($rule, 'route=');
		if ($pos !== false) {
			$rule = substr($rule, $pos + 6);
		} elseif (strpos($rule, 'index.php') !== false) {
			// An admin URL without route= opens the dashboard.
			$rule = $defaultRoute;
		}

		$pos = strpos($rule, '&');
		if ($pos !== false) {
			$rule = substr($rule, 0, $pos);
		}

		return str_replace('|', '.', trim($rule, " \t/"));
	}
}
