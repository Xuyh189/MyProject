<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------

namespace Home\Controller;
use OT\DataDictionary;
use Think\Model;

/**
 * 文档模型控制器
 * 文档模型列表和详情
 */
class ArticleController extends HomeController {

    /* 文档模型频道页 */
	public function index(){
		/* 分类信息 */
		$category = $this->category();
		//频道页只显示模板，默认不读取任何内容
		//内容可以通过模板标签自行定制

		/* 模板赋值并渲染模板 */
		$this->assign('category', $category);
		$this->display($category['template_index']);
	}
	/* 获取当前文档分类的路径（面包屑） */
	public function get_crumbs($catid,$ext=''){
 		$cat = M('Category');
		$here = "首页";
		$uplevels = $cat->field("id,title,pid")->where("id=$catid")->find();
		if($uplevels['pid'] != 0)
		$here .= $this->get_up_levels($uplevels['pid']);
		$here .= ' - '.$uplevels['title'];
		if($ext != '') $here .= ' -> '.$ext;
        return $here;
    }

	/* 文档分类递归（面包屑） */
	public function get_up_levels($cateid){
		$cat = M('Category');
		$here = '';
		$uplevels = $cat->field("id,title,pid")->where("id=$cateid")->find();
		// $channel = M('Channel')->where("category_id=".$uplevels['id'])->find();
		$here .= ' - '.$uplevels['title'];
		if($uplevels['pid'] != 0){
			$here = $this->get_up_levels($uplevels['pid']).$here;
	 	}
	 	return $here;
	}

	/**
	* 导航递归
	* @param array   $list   要转换的数据集
	* @param array   $child  子数据集
	* @param integer $pid    父级ID
	* @return array  导航树
	*/
	public function  allNavs($list,$pid = 0,$child = '_child'){
	
		$arr = array();

		foreach ($list as $key => $v) {
			//判断，如果$v['pid'] == $pid的则压入数组Child
			if ($v['pid'] == $pid) {
				//递归执行
				$v[$name] = self::allNavs($list,$v['id'],$child);
				$arr[] = $v;
			}
		}
		return $arr;
	}



	/* 文档模型列表页 */
	public function lists($p = 1){
		/* 分类信息 */
		$category = $this->category();

		/* 获取当前分类列表 */
		$Document = D('Document');
		$list = $Document->page($p, $category['list_row'])->lists($category['id']);
		if(false === $list){
			$this->error('获取列表数据失败！');
		}

		/* 获取总记录数*/
		$listCount = $Document->listCount($category[id]);

		/* 获取文档面包屑 */
		$this->assign('crumbs', $this->get_crumbs($category['id']));
		/*顶部导航栏*/

        $menu   = D('channel')->getNavTree(0);
        $this->assign('menu',$menu);
		/* 获取导航目录树 */
	    $channel = D('Channel');
	    $navId = $channel->getNavId($category['id']);
	    $topNav = $channel->findParent($navId);
        $navLists = $channel->getNavTree($topNav['id']); 
        // echo "<pre>";
        // print_r($navId);
        // print_r($navLists);die;

		/* 获取文档面包屑 */
		$this->assign('crumbs', $this->get_crumbs($category['id']));

		/* 模板赋值并渲染模板 */
		$this->assign('sub_title',$navId['title']);
		$this->assign('category', $category);
		$this->assign('navLists', $navLists);
		$this->assign('topNav',$topNav);
		$this->assign('list', $list);
		$this->assign('listCount',$listCount);
		$this->assign('page',$p);
		$this->assign('totalPages',ceil($listCount/$category['list_row']));
		$this->display($category['template_lists']);
	}

	/* 文档模型详情页 */
	public function detail($id = 0, $p = 1){
 

		/* 标识正确性检测 */
		if(!($id && is_numeric($id))){
			$this->error('文档ID错误！');
		}

		/* 页码检测 */
		$p = intval($p);
		$p = empty($p) ? 1 : $p;

		/* 获取详细信息 */
		$Document = D('Document');
		$info = $Document->detail($id);
		if(!$info){
			$this->error($Document->getError());
		}
		
		/* 分类信息 */
		$category = $this->category($info['category_id']);

		/* 获取模板 */
		if(!empty($info['template'])){//已定制模板
			$tmpl = $info['template'];
		} elseif (!empty($category['template_detail'])){ //分类已定制模板
			$tmpl = $category['template_detail'];
		} else { //使用默认模板
			$tmpl = 'Article/'. get_document_model($info['model_id'],'name') .'/detail';
		}

		/*顶部导航栏*/

        $menu   = D('channel')->getNavTree(0);
        $this->assign('menu',$menu);
        
		/* 更新浏览数 */
		$map = array('id' => $id);
		$Document->where($map)->setInc('view');

		/* 获取文档面包屑 */
		$this->assign('crumbs', $this->get_crumbs($info['category_id']));

		/* 获取导航目录树 */
	    $channel = D('Channel');
	    $navId = $channel->getNavId($category['id']);
	    $topNav = $channel->findParent($navId);
        $navLists = $channel->getNavTree($topNav['id']); 

		/* 模板赋值并渲染模板 */
		$this->assign('navLists',$navLists);
		$this->assign('topNav',$topNav);
		$this->assign('category', $category);
		$this->assign('info', $info);
		$this->assign('page', $p); //页码
		$this->display($tmpl);
	}

	/* 文档分类检测 */
	private function category($id = 0){
		/* 标识正确性检测 */
		$id = $id ? $id : I('get.category', 0);
		if(empty($id)){
			$this->error('没有指定文档分类！');
		}

		/* 获取分类信息 */
		$category = D('Category')->info($id);
		if($category && 1 == $category['status']){
			switch ($category['display']) {
				case 0:
					$this->error('该分类禁止显示！');
					break;
				//TODO: 更多分类显示状态判断
				default:
					return $category;
			}
		} else {
			$this->error('分类不存在或被禁用！');
		}
	}

}
