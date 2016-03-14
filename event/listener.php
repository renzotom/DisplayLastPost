<?php
/**
 *
 * Display Last Post extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2013 phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace Aurelienazerty\DisplayLastPost\event;

/**
 * Event listener
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\user $user */
	protected $user;
	
	/** @var int */
	private $last_post_id;

	/**
	 * Constructor
	 *
	 * @param \phpbb\db\driver\driver_interface    $db               DBAL object
	 * @param \phpbb\config\config	$config	Config object
	 * @param \phpbb\user	$user	user object
	 * @return \Aurelienazerty\DisplayLastPost\event\listener
	 * @access public
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\config\config $config, \phpbb\user $user)
	{
		$this->user = $user;
		$this->config = $config;
		$this->db = $db;
	}

	/**
	 * Assign functions defined in this class to event listeners in the core
	 *
	 * @return array
	 * @static
	 * @access public
	 */
	static public function getSubscribedEvents()
	{
		return array(
			'core.viewtopic_get_post_data'		=> 'modify_viewtopic_post_list',
			'core.viewtopic_modify_post_row'	=> array('modify_first_post_of_the_topic', -2710),
			'core.acp_board_config_edit_add'	=> 'acp_board_post_config',
		);
	}
	
	/**
	 * Modify the firt post of the topic 
	 * (only if it's not the first page)
	 *
	 * @param object $event The event object
	 *
	 * @return null
	 * @access public
	 */
	public function modify_first_post_of_the_topic($event)
	{
		$start = $event['start'];
		if ($this->config['display_last_post_show'] && $start > 0 && $event["post_row"]["POST_ID"] == $this->last_post_id)
		{
			$this->user->add_lang_ext('Aurelienazerty/DisplayLastPost', 'display_last_post');
			$post_row = $event['post_row'];
			$post_row['MESSAGE'] = '<p style="font-weight: bold; font-size: 1em;">' . $this->user->lang['DISPLAY_LAST_POST_TEXT'] . $this->user->lang['COLON'] . '</p>' . $post_row['MESSAGE'];
			$event['post_row'] = $post_row;
		}
	}

	/**
	 * Modify the list of post, to add the previous post of the lastest page
	 * (only if it's not the first page)
	 *
	 * @param object $event The event object
	 *
	 * @return null
	 * @access public
	 */
	public function modify_viewtopic_post_list($event)
	{
		$topic_data = $event['topic_data'];
		$start = $event['start'];
		$sql_ary = $event['sql_ary'];
		$post_list = $event['post_list'];
		if ($this->config['display_last_post_show'] && $start > 0)
		{
			$new_post_list = array();
			foreach ($post_list as $key => $value)
			{
				$new_post_list[$key+1] = $value;
			}
			$sql_array = array(
				'SELECT'	=> 'p.post_id',
				'FROM'		=> array(
					POSTS_TABLE	=> 'p',
				),
				'WHERE' => 'p.topic_id = ' . (int) $topic_data['topic_id'] . ' AND p.post_id < ' . (int) $post_list[0] . ' AND post_visibility =  1',
				'ORDER_BY' => 'p.post_id DESC'
			);
			$sql = $this->db->sql_build_query('SELECT', $sql_array);
			$result = $this->db->sql_query_limit($sql, 1);
			//Array dereferencing only for php >= 5.4
			$fetchrow = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
			$this->last_post_id = $fetchrow['post_id'];
			$new_post_list[0] = $this->last_post_id; 
			$event['post_list'] = $new_post_list;
			$sql_ary['WHERE'] = $this->db->sql_in_set('p.post_id', $new_post_list) . ' AND u.user_id = p.poster_id';
			$event['sql_ary'] = $sql_ary;
		}
	}

	/**
	 * ACP fonction : Adding radio in the post config to switch on/off "Display Last Post" feature
	 *
	 * @param object $event The event object
	 *
	 * @return null
	 * @access public
	 */
	public function acp_board_post_config($event)
	{
		if ($event['mode'] == 'post')
		{
			$display_vars = $event['display_vars'];
			$add_config_var = array(
				'display_last_post_show'	=> array(
					'lang' => 'DISPLAY_LAST_POST_SHOW',
					'validate' => 'bool',
					'type' => 'radio: yes_no',
					'explain' => true,
				)
			);
			$display_vars['vars'] = phpbb_insert_config_array($display_vars['vars'], $add_config_var, array('after' =>'posts_per_page'));
			$event['display_vars'] = array('title' => $display_vars['title'], 'vars' => $display_vars['vars']);
		}
	}
}
