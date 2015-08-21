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

		$createContent = (!empty($val['admin_id']) ? true : false);

		if ($createContent) {
			$adminId = (int) $val['admin_id'];
			$tmp = PHPFOX_DIR_FILE . 'static/';
			$photos = [];
			$response = file_get_contents('http://www.pexels.com/search/summer.js?page=1');
			preg_match_all('/https:\/\/static\.pexels\.com\/photos\/([0-9]+)\/([a-zA-Z0-9-]+).jpg/i', $response, $matches);
			define('PHPFOX_HTML5_PHOTO_UPLOAD', true);
			define('PHPFOX_FILE_DONT_UNLINK', true);
			foreach ($matches[0] as $url) {
				$parts = explode('/', $url);
				$name = ucwords(str_replace('-', ' ', explode('.', $parts[count($parts) - 1])[0]));
				$path = $tmp . md5($url) . '.jpg';
				file_put_contents($path, file_get_contents($url));
				if (!file_exists($path)) {
					continue;
				}
				register_shutdown_function(function () use ($path) {
					if (file_exists($path)) {
						unlink($path);
					}
				});
				$photos[] = [
					'name' => md5($url) . '.jpg',
					'title' => $name,
					'path' => $path
				];
			}

			/**
			 * @copyright http://commments.com/
			 */
			$comments = [
				'This shot blew my mind.',
				'It\'s fab not just fabulous!',
				"This is delightful work m8",
				"I think I'm crying. It's that amazing.",
				"Incredibly thought out! I'm in!",
				"Let me take a nap... great shot, anyway.",
				"You just won the internet!",
				"Very bold style mate",
				"Mission accomplished. It's sleek :)",
				"Contrast.",
				"This is strong and excellent m8",
				"Nice use of light in this shot!!",
				"I want to learn this kind of shape! Teach me.",
				"YEW!",
				"Engaging work you have here."
			];
		}

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
					'email' => uniqid() . preg_replace("/[^a-zA-Z0-9@\.]+/", "", $me->email),
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
					// 'country_iso' => $me->nationality
				], ['user_id' => $u->id]);

				\User_Service_Process::instance()->uploadImage($u->id, true, $me->picture->large, true);

				if ($createContent) {
					// Friend request to the admin
					\Friend_Service_Request_Process::instance()->add($u->id, $adminId);

					/*
					\User_Service_Auth::instance()->setUserId($u->id);
					$success = \Mail_Service_Process::instance()->add([
						'to' => $adminId,
						'message' => 'lorem ipsum...'
					]);

					if (!$success) {
						throw new \Exception(implode(' ', \Phpfox_Error::get()));
					}
					*/

					$iteration = rand(0, (count($photos) - 1));
					$photo = $photos[$iteration];
					$sHTML5TempFile = $photo['path'];
					$fn = $photo['name'];
					unset($_FILES['image']);
					$_FILES['image'] = array(
						'name' => array($fn),
						'type' => array('image/jpeg'),
						'tmp_name' => array($sHTML5TempFile),
						'error' => array(0),
						'size' => array(filesize($sHTML5TempFile))
					);

					if (!file_exists($sHTML5TempFile)) {
						d($photo);
						exit('Cannot find file: ' . $sHTML5TempFile);
					}

					$oFile = \Phpfox_File::instance();
					if ($aImage = $oFile->load('image[0]', array(
							'jpg',
							'gif',
							'png'
						)
					)
					) {
						$iId = \Photo_Service_Process::instance()->add($u->id, array_merge([
							'title' => $photo['title']
						], $aImage));

						if ($sFileName = $oFile->upload('image[0]',
							Phpfox::getParam('photo.dir_photo'),
							$iId,
							true
						)
						) {

						} else {
							throw new \Exception('Error(1): ' . implode('', \Phpfox_Error::get()));
						}

						// Get the current image width/height
						$aSize = getimagesize(Phpfox::getParam('photo.dir_photo') . sprintf($sFileName, ''));

						// Update the image with the full path to where it is located.
						$aUpdate = array(
							'destination' => $sFileName,
							'width' => $aSize[0],
							'height' => $aSize[1],
							'server_id' => Phpfox_Request::instance()->getServer('PHPFOX_SERVER_ID'),
							'allow_rate' => (empty($aVals['album_id']) ? '1' : '0'),
							'description' => (empty($aVals['description']) ? null : $aVals['description'])
						);

						\Photo_Service_Process::instance()->update($u->id, $iId, $aUpdate);
						$oImage = \Phpfox_Image::instance();
						foreach (Phpfox::getParam('photo.photo_pic_sizes') as $iSize) {
							// Create the thumbnail
							if ($oImage->createThumbnail(Phpfox::getParam('photo.dir_photo') . sprintf($sFileName, ''), Phpfox::getParam('photo.dir_photo') . sprintf($sFileName, '_' . $iSize), $iSize, $iSize, true, ((Phpfox::getParam('photo.enabled_watermark_on_photos') && Phpfox::getParam('core.watermark_option') != 'none') ? (Phpfox::getParam('core.watermark_option') == 'image' ? 'force_skip' : true) : false)) === false) {
								throw new \Exception('Error(2): ' .implode('', \Phpfox_Error::get()));
							}
						}

						// Add a feed
						$iFeedId = \Feed_Service_Process::instance()->add('photo', $iId, 0, 0, 0, $u->id);

						// Comment and like the photo
						$users = new \Api\User();
						foreach ($users->get() as $user) {
							\Like_Service_Process::instance()->add('photo', $iId, $user->id);

							$comment = $comments[rand(0, (count($comments) - 1))];
							\Comment_Service_Process::instance()->add([
								'parent_id' => 0,
								'type' => 'photo',
								'item_id' => $iId,
								'text' => $comment
							], $user->id);
						}


						// Poke the admin
						\User_Service_Auth::instance()->setUserId($u->id);
						\Poke_Service_Process::instance()->sendPoke($adminId);

					} else {
						throw new \Exception(implode('', \Phpfox_Error::get()));
					}
				}
			}
		} catch (\Exception $e) {
			return [
				'error' => $e->getMessage()
			];
		}

		\Phpfox_Cache::instance()->remove();

		return [
			'success' => true
		];
	}

	$db = new \Core\Db();
	$options = [];
	$admins = $db->select('*')->from(':user')->where(['user_group_id' => ADMIN_USER_ID])->all();
	$options[0] = 'Select:';
	foreach ($admins as $admin) {
		$options[$admin['user_id']] = $admin['full_name'];
	}

	return $controller->render('build.html', [
		'admins' => $options
	]);
});