<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Blog extends Controller_Template {
	public $template = 'blog/template';
	
	public function before()
	{
		return parent::before();
	}
	
	public function action_index()
	{
		$config = Weby::config('blog');
		$title = $config->title;
		$this->template->title = 'First Blog - '.$title;
		$this->template->content= 'Hello, the first blog.';
	}
	
	public function action_test()
	{
		// 不使用模板渲染
		$this->auto_render = FALSE;
		
		$this->request->response = '你好，Weby framework！<br />';
		
		// 性能跟测试
		$count = 0;
		for ($i=0; $i<5000000; $i++) {
			$count += 1;
		}
//		$this->request->response .= '循环5百万次时间（微秒）：' . (microtime(TRUE) - WEBY_START_TIME) . '<br />';
		
		// 数组通过 += 合并
//		$arr = array('A'=>1, 'B'=>2);
//		$arr += array('C'=>3, 'D'=>4);
//		$this->request->response .= print_r($this->request->param(), TRUE).$this->request->controller.$this->request->action.print_r($arr, TRUE).print_r($_GET, TRUE).print_r($_POST, TRUE);
		
		// $this->request-uri() 返回当前的 uri
//		$this->request->response = $this->request->uri();

		//		echo __FUNCTION__;
//		print_r(Weby::find_file());
//		Weby::cache('foo', 'hello, world');
//		echo '<pre>';print_r(get_declared_classes());

		$this->request->response .= '<br />Memory usage: {memory_usage} <br />Execution time: {execution_time}';
	}
	
	public function after()
	{
		return parent::after();
	}
}