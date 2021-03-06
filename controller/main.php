<?php
/**
*
* @package Board3 Portal v2.1
* @copyright (c) 2013 Board3 Group ( www.board3.de )
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace board3\portal\controller;

class main
{
	/**
	* phpBB Config object
	* @var \phpbb\config\config
	*/
	protected $config;

	/**
	* Board3 Portal controller helper
	* @var \board3\portal\controller\helper
	*/
	protected $controller_helper;

	/**
	* Template object
	* @var \phpbb\template
	*/
	protected $template;

	/**
	* User object
	* @var \phpbb\user
	*/
	protected $user;

	/**
	* phpBB root path
	* @var string
	*/
	protected $phpbb_root_path;

	/**
	* PHP file extension
	* @var string
	*/
	protected $php_ext;

	/**
	* Portal root path
	* @var string
	*/
	protected $root_path;

	/**
	* Portal includes path
	* @var string
	*/
	protected $includes_path;

	/**
	* phpBB path helper
	* @var \phpbb\path_helper
	*/
	protected $path_helper;

	/**
	* Portal modules count
	* @var array
	*/
	protected $module_count;

	/**
	* Portal modules array
	* @var array
	*/
	protected $portal_modules;

	/**
	* Constructor
	* NOTE: The parameters of this method must match in order and type with
	* the dependencies defined in the services.yml file for this service.
	* @param \phpbb\config\config $config phpBB Config object
	* @param \board3\portal\controller\helper $controller_helper Controller helper
	* @param \phpbb\template $template Template object
	* @param \phpbb\user $user User object
	* @param \phpbb\path_helper $path_helper phpBB path helper
	* @param string $phpbb_root_path phpBB root path
	* @param string $php_ext PHP file extension
	* @param string $config_table Board3 config table
	* @param string $modules_table Board3 modules table
	*/
	public function __construct($config, $controller_helper, $template, $user, $path_helper, $phpbb_root_path, $php_ext, $config_table, $modules_table)
	{
		global $portal_root_path;

		$this->config = $config;
		$this->controller_helper = $controller_helper;
		$this->template = $template;
		$this->user = $user;
		$this->path_helper = $path_helper;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;

		$this->includes_path = $phpbb_root_path . 'ext/board3/portal/includes/';
		$this->root_path = $phpbb_root_path . 'ext/board3/portal/';
		$portal_root_path = $this->root_path;
		define('PORTAL_MODULES_TABLE', $modules_table);
		define('PORTAL_CONFIG_TABLE', $config_table);

		if (!function_exists('obtain_portal_config'))
		{
			include($this->includes_path . 'constants' . $this->php_ext);
			include($this->includes_path . 'functions_modules' . $this->php_ext);
			include($this->includes_path . 'functions' . $this->php_ext);
		}
	}

	/**
	* Extension front handler method. This is called automatically when your extension is accessed
	* through index.php?ext=example/foobar
	* @return null
	*/
	public function handle()
	{
		$this->controller_helper->run_initial_tasks();

		// Set default data
		$this->portal_modules = obtain_portal_modules();
		$display_online = false;

		/**
		* set up column_count array
		* with this we can hide unneeded parts of the portal
		*/
		$this->module_count = array(
			'total' 	=> 0,
			'top'		=> 0,
			'left'		=> 0,
			'center'	=> 0,
			'right'		=> 0,
			'bottom'	=> 0,
		);

		/**
		* start assigning block vars
		*/
		foreach ($this->portal_modules as $row)
		{
			if (!($module = $this->controller_helper->get_portal_module($row)))
			{
				continue;
			}

			// Load module language file
			$this->controller_helper->load_module_language($module);

			$template_module = $this->get_module_template($row, $module);

			if (empty($template_module))
			{
				continue;
			}

			// Custom Blocks that have been defined in the ACP will return an array instead of just the name of the template file
			$this->controller_helper->assign_module_vars($row, $template_module);

			// Check if we need to show the online list
			$display_online = $this->controller_helper->check_online_list($row['module_classname'], $display_online);

			unset($template_module);
		}

		// Redirect to index if there are currently no active modules
		$this->check_redirect();

		// Assign specific vars
		$this->assign_template_vars();

		// And now to output the page.
		page_header($this->user->lang('PORTAL'), $display_online);

		// foobar_body.html is in ./ext/foobar/example/styles/prosilver/template/foobar_body.html
		$this->template->set_filenames(array(
			'body' => 'portal/portal_body.html'
		));

		$this->make_jumpbox($this->config['board3_display_jumpbox']);

		page_footer();
	}

	/**
	* Get module's template
	*
	* @param array $row Database row of module
	* @param object $module Module object
	*
	* @return mixed False if module is not inside possible columns or if
	*		module shouldn't be shown, otherwise module's template
	*/
	public function get_module_template($row, $module)
	{
		$template_module = false;
		$column = column_num_string($row['module_column']);

		if (in_array($column, array('left', 'right')) && $this->config['board3_' . $column . '_column'])
		{
			++$this->module_count[$column];
			$template_module = $module->get_template_side($row['module_id']);
		}
		else if (in_array($column, array('top', 'center', 'bottom')))
		{
			++$this->module_count[$column];
			$template_module = $module->get_template_center($row['module_id']);
		}

		return $template_module;
	}

	/**
	* Check if portal needs to redirect to index page
	*/
	protected function check_redirect()
	{
		$this->module_count['total'] = sizeof($this->portal_modules);

		if ($this->module_count['total'] < 1)
		{
			redirect(append_sid($this->phpbb_root_path . 'index' . $this->php_ext));
		}
	}

	/**
	* Assign template vars for portal
	*
	* @return null
	*/
	protected function assign_template_vars()
	{
		$this->template->assign_vars(array(
			'S_PORTAL_LEFT_COLUMN'		=> $this->config['board3_left_column_width'],
			'S_PORTAL_RIGHT_COLUMN'		=> $this->config['board3_right_column_width'],
			'S_LEFT_COLUMN'			=> $this->check_module_count('left', $this->config['board3_left_column']),
			'S_CENTER_COLUMN'		=> $this->check_module_count('center'),
			'S_RIGHT_COLUMN'		=> $this->check_module_count('right', $this->config['board3_right_column']),
			'S_TOP_COLUMN'			=> $this->check_module_count('top'),
			'S_BOTTOM_COLUMN'		=> $this->check_module_count('bottom'),
			'S_DISPLAY_PHPBB_MENU'		=> $this->config['board3_phpbb_menu'],
			'B3P_DISPLAY_JUMPBOX'		=> $this->config['board3_display_jumpbox'],
			'T_EXT_THEME_PATH'		=> $this->path_helper->get_web_root_path() . $this->root_path . 'styles/' . $this->user->style['style_path'] . '/theme/',
		));
	}

	/**
	* Check module count and related config setting
	*
	* @param string $column Column to check
	* @param bool $config Config value to check
	*
	* @return bool True if module count is bigger than 0 and $config is true
	*/
	protected function check_module_count($column, $config = true)
	{
		return $this->module_count[$column] > 0 && $config;
	}

	/**
	* Wrapper method for running make_jumpbox
	*
	* @param bool $display Whether jumpbox should be displayed
	* @return null
	*/
	protected function make_jumpbox($display = false)
	{
		if ($display)
		{
			make_jumpbox(append_sid("{$this->phpbb_root_path}viewforum{$this->php_ext}"));
		}
	}
}
