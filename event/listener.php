<?php
/**
*
* @package Board3 Portal v2.1
* @copyright (c) 2014 Board3 Group ( www.board3.de )
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace board3\portal\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\controller\helper */
	protected $controller_helper;

	/** @var \phpbb\path_helper */
	protected $path_helper;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var string phpEx */
	protected $php_ext;

	/**
	* Constructor of Board3 Portal event listener
	*
	* @param \phpbb\auth\auth		$auth	phpBB auth object
	* @param \phpbb\controller\helper	$controller_helper	Controller helper object
	* @param \phpbb\path_helper		$path_helper		phpBB path helper
	* @param \phpbb\template\template	$template		Template object
	* @param \phpbb\user			$user			User object
	* @param string				$php_ext		phpEx
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\controller\helper $controller_helper, \phpbb\path_helper $path_helper, \phpbb\template\template $template, \phpbb\user $user, $php_ext)
	{
		$this->auth = $auth;
		$this->controller_helper = $controller_helper;
		$this->path_helper = $path_helper;
		$this->template = $template;
		$this->user = $user;
		$this->php_ext = $php_ext;
	}

	/**
	* Assign functions defined in this class to event listeners in the core
	*
	* @return array
	*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'			=> 'load_portal_language',
			'core.viewonline_overwrite_location'	=> 'viewonline_page',
			'core.page_header'			=> 'add_portal_link',
		);
	}

	/**
	* Load portal language during user setup
	*
	* @param object $event The event object
	* @return null
	*/
	public function load_portal_language($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'board3/portal',
			'lang_set' => 'portal',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	* Show users as viewing the portals on Who Is Online page
	*
	* @param object $event The event object
	* @return null
	*/
	public function viewonline_page($event)
	{
		if ($event['on_page'][1] == 'app' && strrpos($event['row']['session_page'], 'app.' . $this->php_ext . '/portal') === 0)
		{
			$event['location'] = $this->user->lang('VIEWING_PORTAL');
			$event['location_url'] = $this->controller_helper->route('board3_portal_controller');
		}
	}

	/**
	* Add portal link if user is authed to see it
	*
	* @return null
	*/
	public function add_portal_link()
	{
		if (!$this->auth->acl_get('u_view_portal'))
		{
			return;
		}

		if (strpos($this->user->data['session_page'], '/portal') === false)
		{
			$portal_link = $this->controller_helper->route('board3_portal_controller');
		}
		else
		{
			$portal_link = $this->path_helper->remove_web_root_path($this->controller_helper->route('board3_portal_controller'));
		}

		$this->template->assign_vars(array(
			'U_PORTAL'	=> $portal_link,
		));
	}
}