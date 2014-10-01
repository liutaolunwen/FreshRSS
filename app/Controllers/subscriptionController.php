<?php

/**
 * Controller to handle subscription actions.
 */
class FreshRSS_subscription_Controller extends Minz_ActionController {
	/**
	 * This action is called before every other action in that class. It is
	 * the common boiler plate for every action. It is triggered by the
	 * underlying framework.
	 */
	public function firstAction() {
		if (!$this->view->loginOk) {
			Minz_Error::error(
				403,
				array('error' => array(_t('access_denied')))
			);
		}

		$catDAO = new FreshRSS_CategoryDAO();

		$catDAO->checkDefault();
		$this->view->categories = $catDAO->listCategories(false);
		$this->view->default_category = $catDAO->getDefault();
	}

	/**
	 * This action handles the main subscription page
	 *
	 * It displays categories and associated feeds.
	 */
	public function indexAction() {
		Minz_View::prependTitle(_t('subscription_management') . ' · ');

		$id = Minz_Request::param('id');
		if ($id !== false) {
			$feedDAO = FreshRSS_Factory::createFeedDao();
			$this->view->feed = $feedDAO->searchById($id);
		}
	}

	/**
	 * This action handles the feed configuration page.
	 *
	 * It displays the feed configuration page.
	 * If this action is reached through a POST request, it stores all new
	 * configuraiton values then sends a notification to the user.
	 *
	 * The options available on the page are:
	 *   - name
	 *   - description
	 *   - website URL
	 *   - feed URL
	 *   - category id (default: default category id)
	 *   - CSS path to article on website
	 *   - display in main stream (default: 0)
	 *   - HTTP authentication
	 *   - number of article to retain (default: -2)
	 *   - refresh frequency (default: -2)
	 * Default values are empty strings unless specified.
	 */
	public function feedAction() {
		if (Minz_Request::param('ajax')) {
			$this->view->_useLayout(false);
		}

		$feedDAO = FreshRSS_Factory::createFeedDao();
		$this->view->feeds = $feedDAO->listFeeds();

		$id = Minz_Request::param('id');
		if ($id === false || !isset($this->view->feeds[$id])) {
			Minz_Error::error(
				404,
				array('error' => array(_t('page_not_found')))
			);
			return;
		}

		$this->view->feed = $this->view->feeds[$id];

		Minz_View::prependTitle(_t('rss_feed_management') . ' · ' . $this->view->feed->name() . ' · ');

		if (Minz_Request::isPost()) {
			$user = Minz_Request::param('http_user', '');
			$pass = Minz_Request::param('http_pass', '');

			$httpAuth = '';
			if ($user != '' || $pass != '') {
				$httpAuth = $user . ':' . $pass;
			}

			$cat = intval(Minz_Request::param('category', 0));

			$values = array(
				'name' => Minz_Request::param('name', ''),
				'description' => sanitizeHTML(Minz_Request::param('description', '', true)),
				'website' => Minz_Request::param('website', ''),
				'url' => Minz_Request::param('url', ''),
				'category' => $cat,
				'pathEntries' => Minz_Request::param('path_entries', ''),
				'priority' => intval(Minz_Request::param('priority', 0)),
				'httpAuth' => $httpAuth,
				'keep_history' => intval(Minz_Request::param('keep_history', -2)),
				'ttl' => intval(Minz_Request::param('ttl', -2)),
			);

			invalidateHttpCache();

			if ($feedDAO->updateFeed($id, $values)) {
				$this->view->feed->_category($cat);
				$this->view->feed->faviconPrepare();

				Minz_Request::good(_t('feed_updated'), array('c' => 'subscription', 'params' => array('id' => $id)));
			} else {
				Minz_Request::bad(_t('error_occurred_update'), array('c' => 'subscription'));
			}
		}
	}
}
