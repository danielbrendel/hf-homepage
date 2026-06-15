<?php

/*
    Asatru PHP - Example controller

    Add here all your needed routes implementations related to 'index'.
*/

/**
 * Example index controller
 */
class IndexController extends BaseController {
	const INDEX_LAYOUT = 'layout';

	/**
	 * @var array
	 */
	private $captcha = [];

	/**
	 * Perform base initialization
	 * 
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct(self::INDEX_LAYOUT);

		if ($_SERVER['REQUEST_METHOD'] === 'GET') { 
			$this->captcha = CaptchaModel::createSum(session_id());
			setGlobalCaptcha($this->captcha);
		}
	}

	/**
	 * Handles URL: /
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\ViewHandler
	 */
	public function index($request)
	{
		//Generate and return a view by using the helper
		return parent::view(['content', 'index'], [
			'show_header' => true,
			'downloads' => DownloadsModel::getDownloads(),
			'showcase' => config('showcase'),
			'subscribers' => CacheModel::remember('newsletter.count', getCacheableHours(24), function() { 
				return NewsletterModel::getSubscriberCount(); 
			}),
			'flags' => json_decode(CacheModel::remember('flags.list', getCacheableHours(24), function() {
				return json_encode(FlagsModule::get());
			}))
		]);
	}

	/**
	 * Handles URL: /screenshots
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\ViewHandler
	 */
	public function screenshots($request)
	{
		//Generate and return a view by using the helper
		return parent::view(['content', 'screenshots'], [
			'_meta_title' => 'Screenshots',
			'_meta_description' => 'Get a visual impression of the app',
			'_meta_url' => url('/screenshots')
		]);
	}

	/**
	 * Handles URL: /themes
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\ViewHandler
	 */
	public function themes($request)
	{
		return parent::view(['content', 'themes'], [
			'_meta_title' => 'Themes - The extra spice for your workspace',
			'_meta_description' => 'Download additional themes to personalize your workspace',
			'_meta_url' => url('/themes'),
			'themes' => ThemeModel::getThemes()
		]);
	}

	/**
	 * Handles URL: /videos
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\ViewHandler|Asatru\View\RedirectHandler
	 */
	public function videos($request)
	{
		if (!env('APP_ENABLE_VIDEO_SECTION')) {
			return redirect('/');
		}

		$categories = TutorialsModel::getCategories();
		$videos = TutorialsModel::getAll();
		
		//Generate and return a view by using the helper
		return parent::view(['content', 'videos'], [
			'_meta_title' => 'Videos',
			'_meta_description' => 'Watch videos on HortusFox',
			'_meta_url' => url('/videos'),
			'categories' => $categories,
			'videos' => $videos
		]);
	}

	/**
	 * Handles URL: /faq
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\ViewHandler
	 */
	public function faq($request)
	{
		//Generate and return a view by using the helper
		return parent::view(['content', 'faq'], [
			'faqs' => FaqModel::getEntries(),
			'_meta_title' => 'Frequently Asked Questions',
			'_meta_description' => 'Get answers to frequently asked questions',
			'_meta_url' => url('/faq'),
		]);
	}

	/**
	 * Handles URL: /documentation
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\RedirectHandler
	 */
	public function documentation($request)
	{
		return redirect(env('LINK_DOCUMENTATION'));
	}

	/**
	 * Handles URL: /demo
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\ViewHandler|Asatru\View\RedirectHandler
	 */
	public function demo($request)
	{
		if (!env('DEMO_ENABLE')) {
			return redirect('/');
		}

		$accounts = [];

		for ($i = 0; $i < 10; $i++) {
			$accounts[] = [
				'email' => 'test' . strval($i + 1) . '@gmail.com',
				'password' => 'test'
			];
		}

		//Generate and return a view by using the helper
		return parent::view(['content', 'demo'], [
			'_meta_title' => 'Live Demo',
			'_meta_description' => 'Try the app before installing',
			'_meta_url' => url('/demo'),
			'accounts' => $accounts
		]);
	}

	/**
	 * Handles URL: /support
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\ViewHandler|Asatru\View\RedirectHandler
	 */
	public function support($request)
	{
		if (env('FORUM_ENABLE')) {
			return redirect(env('FORUM_LINK'));
		} else if (env('EXTERNAL_SUPPORT')) {
			return redirect(env('EXTERNAL_LINK'));
		}

		return parent::view(['content', 'support'], [
			'captcha' => $this->captcha,
			'_meta_title' => 'Get Support',
			'_meta_description' => 'Are you having problems? Something is not working? Contact us!',
			'_meta_url' => url('/support'),
		]);
	}

	/**
	 * Handles URL: POST /support
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\ViewHandler
	 */
	public function support_request($request)
	{
		try {
			if (!env('HELPREALM_RESTAPI_ENABLE')) {
				throw new \Exception('This feature is currently disabled.');
			}

			$name = $request->params()->query('name');
			$email = $request->params()->query('email');
			$subject = $request->params()->query('subject');
			$message = $request->params()->query('message');
			$captcha = $request->params()->query('captcha');
			$consent = (bool)$request->params()->query('consent', 0);

			$sum = CaptchaModel::querySum(session_id());
			if ($sum !== $captcha) {
				throw new \Exception('Please enter the correct captcha');
			}
			
			if (!$consent) {
				throw new \Exception('Please consent to our support conditions.');
			}

			SupportModule::request($name, $email, $subject, $message);

			FlashMessage::setMsg('success', 'Your support request was submitted. We will get back to you as soon as possible.');
			return redirect('/support');
		} catch (\Exception $e) {
			FlashMessage::setMsg('error', $e->getMessage());
			return redirect('/support');
		}
	}

	/**
	 * Handles URL: /partnership
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return Asatru\View\ViewHandler
	 */
	public function partnership($request)
	{
		//Generate and return a view by using the helper
		return parent::view(['content', 'partnership'], [
			'show_header' => false
		]);
	}

	/**
	 * Handles URL: /sitemap
	 * 
	 * @param Asatru\Controller\ControllerArg $request
	 * @return mixed
	 */
	public function sitemap($request)
	{
		try {
			$sitemap = SitemapModule::generate();
			
			header('Content-Type: text/xml');
			echo $sitemap;

			exit(0);
		} catch (\Exception $e) {
			return abort(500);
		}
	}
}
