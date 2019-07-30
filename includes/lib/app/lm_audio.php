<?php
namespace lib\app;


class lm_audio
{

	public static $sort_field =
	[
		'id',
		'teacher',
		'audio',
		'teachertxt',
		'teacheraudio',
		'quality',
		'status',

	];

	public static function add_new($_file, $_level_id)
	{
		if(!\dash\user::id())
		{
			\dash\notif::error(T_("User not found"), 'user');
			return false;
		}

		$load_level = \lib\app\lm_level::get($_level_id);

		if(!$load_level || !isset($load_level['lm_audio_id']))
		{
			return false;
		}

		$group_id = \dash\coding::decode($load_level['lm_audio_id']);

		$args                 = [];
		$args['user_id']      = \dash\user::id();
		$args['lm_audio_id']  = $group_id;
		$args['lm_level_id']  = \dash\coding::decode($_level_id);
		$args['teacher']      = null;
		$args['audio']        = $_file;
		$args['teachertxt']   = null;
		$args['teacheraudio'] = null;
		$args['quality']      = null;
		$args['status']       = 'awaiting';
		$args['datecreated']  = date("Y-m-d H:i:s");

		$lm_audio_id = \lib\db\lm_audio::insert($args);

		if(!$lm_audio_id)
		{
			\dash\notif::error(T_("No way to insert data"), 'db');
			return false;
		}

		$return['id'] = \dash\coding::encode($lm_audio_id);

		if(\dash\engine\process::status())
		{
			\dash\notif::ok(T_("Your audio uploaded"));
		}

		return $return;
	}




	public static function edit($_args, $_id)
	{
		\dash\app::variable($_args);

		$result = self::get($_id);

		if(!$result)
		{
			return false;
		}

		$id = \dash\coding::decode($_id);

		$args = self::check($id);

		if($args === false || !\dash\engine\process::status())
		{
			return false;
		}

		if(!\dash\app::isset_request('lm_group_id')) unset($args['lm_group_id']);
		if(!\dash\app::isset_request('lm_level_id')) unset($args['lm_level_id']);
		if(!\dash\app::isset_request('user_id')) unset($args['user_id']);
		if(!\dash\app::isset_request('teacher')) unset($args['teacher']);
		if(!\dash\app::isset_request('audio')) unset($args['audio']);
		if(!\dash\app::isset_request('teachertxt')) unset($args['teachertxt']);
		if(!\dash\app::isset_request('teacheraudio')) unset($args['teacheraudio']);
		if(!\dash\app::isset_request('quality')) unset($args['quality']);
		if(!\dash\app::isset_request('status')) unset($args['status']);
		if(!\dash\app::isset_request('datecreated')) unset($args['datecreated']);

		if(!empty($args))
		{
			$update = \lib\db\lm_audio::update($args, $id);

			$title = isset($args['title']) ? $args['title'] : T_("audio");

			\dash\log::set('editLearnAudio', ['code' => $id]);

			if(\dash\engine\process::status())
			{
				\dash\notif::ok(T_(":val successfully updated", ['val' => $title]));
			}
		}

		return \dash\engine\process::status();
	}


	public static function get($_id)
	{
		$id = \dash\coding::decode($_id);
		if(!$id)
		{
			\dash\notif::error(T_("audio id not set"));
			return false;
		}

		$get = \lib\db\lm_audio::get(['id' => $id, 'limit' => 1]);

		if(!$get)
		{
			\dash\notif::error(T_("Invalid audio id"));
			return false;
		}

		$result = self::ready($get);

		return $result;
	}


	public static function list($_string = null, $_args = [])
	{
		if(!\dash\user::id())
		{
			return false;
		}

		$default_meta =
		[
			'sort'  => null,
			'order' => null,
		];

		if(!is_array($_args))
		{
			$_args = [];
		}

		$_args = array_merge($default_meta, $_args);

		if($_args['sort'] && !in_array($_args['sort'], self::$sort_field))
		{
			$_args['sort'] = null;
		}

		$result            = \lib\db\lm_audio::search($_string, $_args);
		$temp              = [];

		foreach ($result as $key => $value)
		{
			$check = self::ready($value);
			if($check)
			{
				$temp[] = $check;
			}
		}

		return $temp;
	}


	private static function check($_id = null)
	{

		$teacher      = \dash\app::request('teacher');


		$teachertxt   = \dash\app::request('teachertxt');
		if($teachertxt && mb_strlen($teachertxt) >= 500)
		{
			\dash\notif::error(T_("Data is out of range"), 'teachertxt');
			return false;
		}

		$teacheraudio = \dash\app::request('teacheraudio');
		if($teacheraudio && mb_strlen($teacheraudio) >= 500)
		{
			\dash\notif::error(T_("Data is out of range"), 'teacheraudio');
			return false;
		}

		$quality      = \dash\app::request('quality');
		if($quality)
		{
			if(!is_numeric($quality))
			{
				\dash\notif::error(T_("Plase set quality as a number"));
				return false;
			}

			$quality = intval($quality);
			if($quality < 0 || $quality > 100)
			{
				\dash\notif::error(T_("quality is out of range"));
				return false;
			}
		}

		$status = \dash\app::request('status');
		if($status && !in_array($status, ['awaiting', 'spam', 'deleted', 'admindelete', 'approved', 'reject']))
		{
			\dash\notif::error(T_("Invalid status"), 'status');
			return false;
		}


		$args                 = [];
		$args['teacher']      = $teacher;

		$args['teachertxt']   = $teachertxt;
		$args['teacheraudio'] = $teacheraudio;
		$args['quality']      = $quality;
		$args['status']       = $status;

		return $args;
	}



	public static function ready($_data)
	{
		$result = [];
		foreach ($_data as $key => $value)
		{

			switch ($key)
			{
				case 'id':
					if(isset($value))
					{
						$result[$key] = \dash\coding::encode($value);
					}
					else
					{
						$result[$key] = null;
					}
					break;

				case 'file':
					if(!$value)
					{
						$value = \dash\app::static_logo_url();
					}
					$result[$key] = $value;
					break;

				case 'status':
					$result[$key] = $value;
					$result['t'.$key] = T_(ucfirst($value));
					break;

				case 'setting':
					if($value)
					{
						$result[$key] = json_decode($value, true);
					}
					else
					{
						$result[$key] = $value;
					}
					break;

				default:
					$result[$key] = $value;
					break;
			}
		}

		return $result;
	}



}
?>