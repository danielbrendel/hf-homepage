<?php

/**
 * This class represents your controller
 */
class AdminController extends BaseController {
    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        if ((!isset($_GET['token'])) || ($_GET['token'] !== env('APP_ACCESSTOKEN'))) {
            http_response_code(403);
            exit('Access denied.');
        }
    }

    /**
	 * Handles URL: /admin
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\ViewHandler
	 */
    public function index($request)
    {
        $app_settings = AppModel::getSettings();

        return parent::view(['content', 'admin'], [
			'app_settings' => $app_settings
		]);
    }

    /**
	 * Handles URL: /admin/softver/save
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\RedirectHandler
	 */
    public function save_software_version($request)
    {
        $software_version = $request->params()->query('software_version');

        AppModel::saveSetting('software_version', $software_version);

        FlashMessage::setMsg('success', 'Software version was updated!');

        return redirect('/admin?token=' . $_GET['token']);
    }

    /**
	 * Handles URL: /admin/newsletter/toggle
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\RedirectHandler
	 */
    public function toggle_newsletter($request)
    {
        $newsletter_enable = (bool)AppModel::querySetting('newsletter_enable');

        $newsletter_enable = !$newsletter_enable;

        AppModel::saveSetting('newsletter_enable', $newsletter_enable);

        FlashMessage::setMsg('success', (($newsletter_enable) ? 'Newsletter is now enabled!' : 'Newsletter is now disabled!'));

        return redirect('/admin?token=' . $_GET['token']);
    }

    /**
	 * Handles URL: /admin/newsletter/go
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\RedirectHandler
	 */
    public function send_newsletter($request)
    {
        $subject = $request->params()->query('subject');
        $content = $request->params()->query('content');

        $newsletter_token = md5(random_bytes(55) . date('Y-m-d H:i:s'));

        AppModel::saveSetting('newsletter_token', $newsletter_token);
        AppModel::saveSetting('newsletter_subject', $subject);
        AppModel::saveSetting('newsletter_content', $content);

        FlashMessage::setMsg('success', 'Newsletter is now in progress!');

        return redirect('/admin?token=' . $_GET['token']);
    }

    /**
	 * Handles URL: /admin/newsletter/process
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\JsonHandler
	 */
    public function process_newsletter($request)
    {
        try {
            AppModel::processNewsletter();

            return json([
                'code' => 200
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => $e->getMessage()
            ]);
        }
    }

    /**
	 * Handles URL: /admin/newsletter/preview
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\RedirectHandler
	 */
    public function preview_newsletter($request)
    {
        $subject = $request->params()->query('subject');
        $content = $request->params()->query('content');

        return view('mail/newsletter_base', [], ['subject' => $subject, 'content' => $content]);
    }

    /**
	 * Handles URL: /admin/newsletter/clean
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\JsonHandler
	 */
    public function cleanup_unconfirmed($request)
    {
        try {
            NewsletterModel::cleanUnconfirmed();

            return json([
                'code' => 200
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => $e->getMessage()
            ]);
        }
    }

    /**
	 * Handles URL: /admin/social/submit
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\RedirectHandler
	 */
    public function social_submit($request)
    {
        SocialModel::addPost($request->params()->query('content'));

        FlashMessage::setMsg('success', 'Social media post has been added to queue!');

        return redirect('/admin?token=' . $_GET['token']);
    }

    /**
	 * Handles URL: /admin/social/post/{platform}
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\JsonHandler
	 */
    public function social_post($request)
    {
        try {
            $platform = $request->arg('platform');

            SocialModel::publishPost($platform);

            return json([
                'code' => 200
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => $e->getMessage()
            ]);
        }
    }
}
