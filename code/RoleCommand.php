<?php
/**
 * 关于角色权限的脚本
 * @author asif<yadong.wu@chinacache.com>
 * @date 2015-10-09 下午2:14:40
 * @version 1.0.0
 * @copyright Copyright ChinaCache
 */
class RoleCommand extends CConsoleCommand
{
	private $actionStr = "function action";

	/**
	 * 删除所有的action操作
	 * 写着玩的，不可随意执行，会把所有的operation删掉，并且删除这么operation和用户、角色之间的所有关系
	 * 但是也可以随便执行，因为AR模式在这里执行不了，提供个思路，哈哈。
	 */
	public function clearOpers()
	{
		$criteria = new CDbCriteria();
		$criteria->condition = "type = 0";
		$actions = AuthItem::model()->findAll($criteria);

		foreach($actions as $key => $action)
		{
			$criteria_child = new CDbCriteria();
			$criteria_child->condition = "child = '{$action->name}'";
			$flag = ItemChildren::model()->deleteAll($criteria_child);
			if($flag > 0)
			{
				if($action->delete())
				{
					echo "$action->name delete success\n";
				}
				else
				{
					echo "$action->name delete failed\n";
				}
			}
		}
	}

	/**
	 * 判断action操作是否在数据库中已经存在
	 */
	public function isExistOper($oper_name)
	{
		$sql = "select * from authitem where type = 0 and name = '{$oper_name}'";
		$connect = Yii::app()->db;
		$command = $connect->createCommand($sql);
		$oper = $command->queryRow();
		if($oper)
		{
			return true;
		}
		return false;
	}

	/**
	 * 生成action列表
	 */
	public function actionGenerate()
	{
		$auth=Yii::app()->authManager;
		$filePath = dirname(dirname(__FILE__))."/modules";
		$routes = array();
		$file_module = $this->GetFileList($filePath);
		//清空所有路由的redis缓存list
		// Yii::app()->redis->ltrim('route_list', 2, 1);
		// echo "清空列表成功！！！\n\n";
		foreach($file_module as $module)
		{
			$file_controller = $this->GetFileList($filePath."/".$module."/controllers");
			if(!empty($file_controller))
			{
				foreach($file_controller as $controller)
				{
					$file_route = $this->GetAction($filePath."/".$module."/controllers/".$controller);
					if(!empty($file_route))
					{
						foreach($file_route as $route)
						{
							$value = strtolower($module."/".substr($controller, 0, -14)."/".$route);
							if(!$this->isExistOper($value))
							{
								$auth->createOperation($value);
								echo $value."成功插入\n";
							}
							//Yii::app()->redis->lpush('route_list', $value);
						}
					}
				}
			}
		}
		echo "\nsuccess\n";
	}

	/**
	 * 遍历文件夹所有文件
	 */
	public function GetFileList($dir)
	{
		$files = array();
		if(is_dir($dir))
		{
			$handle = opendir($dir);
			if($handle)
			{
				while($file = readdir($handle))
				{
					if($file!='.' && $file!='..' && $file!='srbac'  && $file!='guide')
					{
						array_push($files, $file);
					}
				}
			}
			closedir($handle);
		}
		return $files;
	}

	/**
	 * 读取文件内容，拿到所有的action
	 */
	public function GetAction($filename)
	{
		$handle = fopen($filename, 'r');
		$actions = array();
		if($handle)
		{
			while(!feof($handle))
			{
				$buff = fgets($handle, 1024);
				$action = $this->drawAction($buff);
				if($action && isset($action) && !empty($action))
				{
					array_push($actions, $action);
				}
			}
		}
		fclose($handle);
		return $actions;
	}

	/**
	 * 根据每一行的内容提取action
	 */
	public function drawAction($str)
	{
		if(strlen($str) == 0)
		{
			return false;
		}
		$str = strtolower($str);
		$res = "";
		$route_str = "";
		$tmp_str = strstr($str, $this->actionStr);
		if($tmp_str)
		{
			$route_str = substr($tmp_str, strlen($this->actionStr));
		}
		if($route_str)
		{
			for($i=0; $i<strlen($route_str); $i++)
			{
				if($route_str[$i] == ' ' || $route_str[$i] == '(')
				{
					break;
				}
				$res .= $route_str[$i];
			}
		}
		return $res;
	}
}