<?php

new Core\Route('/admincp/build-stock-users', function(Core\Controller $controller) {

	if ($controller->request->isPost()) {
		$val = $controller->request->get('val');
		if (empty($val['total'])) {
			$val['total'] = 10;
		}

		if ($val['total'] > 1000) {
			$val['total'] = 1000;
		}

		// $response = file_get_contents('http://www.pexels.com/search/summer.js?page=1');
		// preg_match_all('/https:\/\/static\.pexels\.com\/photos\/([0-9]+)\/([a-zA-Z0-9-]+).jpg/i', $response, $matches);

		$data = json_decode(file_get_contents('https://randomuser.me/api/?results=' . $val['total']));
		try {
			$Db = new \Core\Db();
			$ApiUser = new \Api\User();

			if (!defined('PHPFOX_SKIP_MAIL')) {
				define('PHPFOX_SKIP_MAIL', true);
			}

			foreach ($data->results as $user) {
				$me = $user->user;

				$ApiUser->assign([
					'name' => ucwords($me->name->first) . ' ' . ucwords($me->name->last),
					'email' => $me->email,
					'password' => (empty($val['password']) ? uniqid() : $val['password'])
				]);
				$u = $ApiUser->post();

				$day = date('j', $me->dob);
				$month = date('n', $me->dob);
				$year = date('Y', $me->dob);
				$Db->update(':user', [
					'user_name' => $me->username,
					'gender' => ($me->gender == 'female' ? 2 : 1),
					'birthday' => \User_Service_User::instance()->buildAge($day, $month, $year),
					'birthday_search' => $me->dob,
					'joined' => $me->registered,
					'country_iso' => $me->nationality
				], ['user_id' => $u->id]);

				\User_Service_Process::instance()->uploadImage($u->id, true, $me->picture->large, true);
			}
		} catch (\Exception $e) {
			return [
				'error' => $e->getMessage()
			];
		}

		return [
			'success' => true
		];
	}

	return $controller->render('build.html');
});