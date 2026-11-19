<?php
/**
 * Application level Controller
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc.
 * (http://cakefoundation.org)
 * @link http://cakephp.org CakePHP(tm) Project
 * @package app.Controller
 * @since CakePHP(tm) v 0.2.9
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
App::uses('Controller', 'Controller');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');
App::uses('Xml', 'Utility');
App::uses('ConnectionManager', 'Model');
/** adding new PDF plug in **/
Configure::write('CakePdf', array(
	'engine' => 'CakePdf.WkHtmlToPdf', 
	'binary' => Configure::read('WkHtmlToPdfPath'), 
	'crypto' => 'CakePdf.Pdftk', 	
	'options' => array(
		'print-media-type' => false, 
		'outline' => false, 
		'dpi' => 96, 
		'header-html' => Router::url('/', true) . 'files/pdf_header.html', 
		'footer-center' => 'Page [page] of [toPage]', 
		'footer-right' => 'Confidential Document. All rights reserved.', 
		'footer-font-size' => '8', 
		'footer-line' => true, 
		'header-line' => true,
		'enable-local-file-access' => true,
		'header-font-name' => 'Trebuchet MS', 'footer-font-name' => 'Trebuchet MS',), 
		'margin' => array(
			'bottom' => 10,
			'left' => 10,
			'right' => 10,
			'top' => 25
		),
	'title' => 'Generated via FlinkISO',
	'orientation' => 'portrait',
	'download' => true,)
);
/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package app.Controller
 * @link http://book.cakephp.org/2.0/en/controllers.html#the-app-controller
 */
App::import('Sanitize');
class AppController extends Controller {
	public $components = array('RequestHandler', 'Session','Gzip.Gzip');
	public $helpers = array('Js', 'Session', 'Paginator');
	public $user = null;
	public $userids = null;
	public $message_count = null;
	public $notification_count = null;
	public $branchIDYes = false;
	
	public function _check_login() {		
		Configure::write("files", WWW_ROOT . 'files' . DS . $this->Session->read('User.company_id'));
		Configure::write("files_url", Router::url('/', true) . 'files/' . $this->Session->read('User.company_id'));		
		Configure::write("path", WWW_ROOT . 'files' . DS . $this->Session->read('User.company_id') . DS . $this->request->controller);
		Configure::write("url", Router::url('/', true) . 'files/' . $this->Session->read('User.company_id') . '/' . $this->request->controller);
		Configure::write("common_path", 'files' . DS . $this->Session->read('User.company_id') . DS . $this->request->controller);		
		$ignore = array('install_updates', 'register','activate', 'send_otp', 'generate_invoice', 'renew', 'invoices', 'check_invoice_date', 'login', 'logout', 'forgot_password', 'reset_password', 'save_doc','onlyofficechk','save_template','save_rec_doc','save_custom_docs','save_file','save_custom_table_doc','get_password_change_remind','opt_check','json','xml','return_user_list','codigo_nc_number','codigo_ncp_number','codigo_m_number','metrologia');
		
		if (empty($this->Session->read('User.id')) && !in_array($this->action, $ignore)) {
			if($this->request->is('ajax'))echo "Session expired. Please login again.";
			if($this->request->action != 'dashboard') $this->Session->setFlash(__('Login to continue.'));
			$this->redirect(array('controller' => 'users', 'action' => 'login'));
		}else{
			$ignore = array('install_updates', 'register','activate', 'send_otp', 'generate_invoice', 'renew', 'invoices', 'check_invoice_date', 'login', 'logout', 'forgot_password', 'reset_password', 'save_doc','onlyofficechk','save_template','save_rec_doc','save_custom_docs','save_file','save_custom_table_doc','get_password_change_remind','opt_check','json','xml','return_user_list','codigo_nc_number','codigo_ncp_number','codigo_m_number','metrologia');
			if (empty($this->Session->read('User.id')) && !in_array($this->action, $ignore)) {
				try{
					$this->loadModel('UserSession');
					$this->UserSession->read(null,$this->Session->read('User.user_session_id'));
					$this->UserSession->set('end_time',date('Y-m-d H:i:d'));
					if($this->Session->read('User.id'))$this->UserSession->save();
					$base = explode('/' , $this->request->base);
					if($this->Session->read('User.dir_name') != $base[count($base)-1]){
						$ignore = array('login', 'logout','onlyofficechk','clean_table_names');
						if(!in_array($this->action,$ignore)){
							$this->Session->write('User.id', NULL);
							$this->Session->destroy('User');
							$this->redirect(array('controller' => 'users', 'action' => 'logout')); 
						}
					}
				}catch(Exception $e){

				}
			}
		}
		if($this->Session->read('User.user_session_id')){
			$this->loadModel('UserSession');
			$this->UserSession->read(null,$this->Session->read('User.user_session_id'));
			$this->UserSession->set('end_time',date('Y-m-d H:i:s'));
			$this->UserSession->save(); 
		}
	}
	
	public function beforeRender() {
		$this->_check_login();
		if($this->Session->read('User')){
			$this->_table_menu();
			$this->_check_lock();
			if($this->action == 'view')$this->_view();
			if($this->action == 'json')$this->_json();
			// $this->_get_approver_list();
			$this->_find_parent();
			$ignoreControllerSotList = array('custom_tables','standards','qc_document_categories','branches','departments','designations');
			if(!in_array($this->request->controller, $ignoreControllerSotList)){
				$this->_sort();	
			}
			
		}
		
		if($this->action == 'json'){
			$callHeaders = getallheaders();
			
			if(isset($callHeaders)){
				$this->loadModel('User');
				if((isset($callHeaders['user']) && !empty($callHeaders['user'])) && (isset($callHeaders['password']) && !empty($callHeaders['password']))){
					$this->User->virtualFields = array(
						'emp_status' => 'select `employment_status` from `employees` where `employees`.`id` LIKE User.employee_id  LIMIT 1'
					);
					
					$user = $this->User->find('count',array(
						'conditions'=>array(
							'User.username'=>$callHeaders['user'],
							'User.password'=>trim(Security::hash($callHeaders['password'], 'md5', true)),
							'User.status'=>1,
							'User.is_mr'=>1,
							'User.emp_status'=>1
						)
					));
					if($user){						
						
					}else{
						$this->response->statusCode(401);
						$this->response->type('json');
						$this->set('_serialize', ['error']);
						$this->set('error', ['message' => 'Unauthorized']);
						$this->response->send();
						exit;
					}
				}else{
					$this->response->statusCode(401);
					$this->response->type('json');
					$this->set('_serialize', ['error']);
					$this->set('error', ['message' => 'Unauthorized']);
					$this->response->send();
					exit;
				}
			}else{
				$this->response->statusCode(401);
				$this->response->type('json');
				$this->set('_serialize', ['error']);
				$this->set('error', ['message' => 'Unauthorized']);
				$this->response->send();
				exit;
			}
		}

		if($this->action == 'xml'){
			$callHeaders = getallheaders();
			
			if(isset($callHeaders)){
				$this->loadModel('User');
				if((isset($callHeaders['user']) && !empty($callHeaders['user'])) && (isset($callHeaders['password']) && !empty($callHeaders['password']))){
					$this->User->virtualFields = array(
						'emp_status' => 'select `employment_status` from `employees` where `employees`.`id` LIKE User.employee_id  LIMIT 1'
					);
					
					$user = $this->User->find('count',array(
						'conditions'=>array(
							'User.username'=>$callHeaders['user'],
							'User.password'=>trim(Security::hash($callHeaders['password'], 'md5', true)),
							'User.status'=>1,
							'User.is_mr'=>1,
							'User.emp_status'=>1
						)
					));
					if($user){						
						
					}else{
						$this->response->statusCode(401);
						$this->response->type('json');
						$this->set('_serialize', ['error']);
						$this->set('error', ['message' => 'Unauthorized']);
						$this->response->send();
						exit;
					}
				}else{
					$this->response->statusCode(401);
					$this->response->type('json');
					$this->set('_serialize', ['error']);
					$this->set('error', ['message' => 'Unauthorized']);
					$this->response->send();
					exit;
				}
			}else{
				$this->response->statusCode(401);
				$this->response->type('json');
				$this->set('_serialize', ['error']);
				$this->set('error', ['message' => 'Unauthorized']);
				$this->response->send();
				exit;
			}
		}
		$skipcontrollers = array('branches','departments','designations','employees','users','standards','clauses','custom_tables','approval_processes');
		if(!in_array($this->request->controller,$skipcontrollers))$this->_fetch_approval_steps();	
	}

	public function _find_parent(){
		if($this->action == 'add'){
			$this->loadModel('CustomTable');
			$customTable = $this->CustomTable->find('first',array('fields'=>array('CustomTable.id','CustomTable.custom_table_id','CustomTable.qc_document_id'),'conditions'=>array('CustomTable.id'=>$this->request->params['named']['custom_table_id']),'recursive'=>-1));
			if($customTable['CustomTable']['custom_table_id']){
				$parentCustomTable = $this->CustomTable->find('first',array('fields'=>array('CustomTable.id','CustomTable.custom_table_id','CustomTable.qc_document_id','CustomTable.table_name'),'conditions'=>array('CustomTable.id'=>$customTable['CustomTable']['custom_table_id']),'recursive'=>-1));
			}else{
				$qcDoc = $this->CustomTable->QcDocument->find('first',array('recursive'=>-1, 'fields'=>array('QcDocument.title', 'QcDocument.id','QcDocument.parent_document_id'), 'conditions'=>array( 'QcDocument.id'=>$customTable['CustomTable']['qc_document_id'])));
				if($qcDoc){					
					$parentCustomTable = $this->CustomTable->find('first',array('fields'=>array('CustomTable.id','CustomTable.custom_table_id','CustomTable.qc_document_id','CustomTable.table_name'),'conditions'=>array('CustomTable.qc_document_id'=>$qcDoc['QcDocument']['parent_document_id']),'recursive'=>-1)); 
				}
			}
			if($parentCustomTable && $this->request->is('ajax') == false){
				$this->Session->setFlash('You can not add data directly to this table. Redirecting parent table.'); 
				$this->redirect(array('controller' => $parentCustomTable['CustomTable']['table_name'], 'action' => 'index','custom_table_id'=>$parentCustomTable['CustomTable']['id'],'qc_document_id'=>$parentCustomTable['CustomTable']['qc_document_id'])); 
			}
		}
		if($this->action == 'edit'){
			//check if request data as paret_id
			// if yes, check if custom table has parent 
			// if no, check if custom table qc document has parent
			if($this->request->data[Inflector::classify($this->request->controller)]['parent_id']){
				$this->loadModel('CustomTable');
				$customTable = $this->CustomTable->find('first',array('fields'=>array('CustomTable.id','CustomTable.custom_table_id','CustomTable.qc_document_id'),'conditions'=>array('CustomTable.id'=>$this->request->params['named']['custom_table_id']),'recursive'=>-1));
				if($customTable['CustomTable']['custom_table_id']){
					$parentCustomTable = $this->CustomTable->find('first',array('fields'=>array('CustomTable.id','CustomTable.custom_table_id','CustomTable.qc_document_id','CustomTable.table_name'),'conditions'=>array('CustomTable.id'=>$customTable['CustomTable']['custom_table_id']),'recursive'=>-1));
				}else{
					$qcDoc = $this->CustomTable->QcDocument->find('first',array('recursive'=>-1, 'fields'=>array('QcDocument.title', 'QcDocument.id','QcDocument.parent_document_id'), 'conditions'=>array( 'QcDocument.id'=>$customTable['CustomTable']['qc_document_id'])));
					if($qcDoc){						
						$parentCustomTable = $this->CustomTable->find('first',array('fields'=>array('CustomTable.id','CustomTable.custom_table_id','CustomTable.qc_document_id','CustomTable.table_name'),'conditions'=>array('CustomTable.qc_document_id'=>$qcDoc['QcDocument']['parent_document_id']),'recursive'=>-1)); 
					}
				}
				if($parentCustomTable){
					$this->set('parent_table_name',$parentCustomTable['CustomTable']['table_name']);
				}
			}else{
				
			}
		}

		if($this->action == 'view'){
			//check if request data as paret_id
			// if yes, check if custom table has parent 
			// if no, check if custom table qc document has parent
			if(isset(Inflector::singularize(Inflector::variable($this->request->controller))[Inflector::classify($this->request->controller)]['parent_id']) && Inflector::singularize(Inflector::variable($this->request->controller))[Inflector::classify($this->request->controller)]['parent_id']){
				$this->loadModel('CustomTable');
				$customTable = $this->CustomTable->find('first',array('fields'=>array('CustomTable.id','CustomTable.custom_table_id','CustomTable.qc_document_id'),'conditions'=>array('CustomTable.id'=>$this->request->params['named']['custom_table_id']),'recursive'=>-1));
				if($customTable['CustomTable']['custom_table_id']){
					$parentCustomTable = $this->CustomTable->find('first',array('fields'=>array('CustomTable.id','CustomTable.custom_table_id','CustomTable.qc_document_id','CustomTable.table_name'),'conditions'=>array('CustomTable.id'=>$customTable['CustomTable']['custom_table_id']),'recursive'=>-1));
				}else{
					$qcDoc = $this->CustomTable->QcDocument->find('first',array('recursive'=>-1, 'fields'=>array('QcDocument.title', 'QcDocument.id','QcDocument.parent_document_id'), 'conditions'=>array( 'QcDocument.id'=>$customTable['CustomTable']['qc_document_id'])));
					if($qcDoc){						
						$parentCustomTable = $this->CustomTable->find('first',array('fields'=>array('CustomTable.id','CustomTable.custom_table_id','CustomTable.qc_document_id','CustomTable.table_name'),'conditions'=>array('CustomTable.qc_document_id'=>$qcDoc['QcDocument']['parent_document_id']),'recursive'=>-1)); 
					}
				}
				if($parentCustomTable){
					$this->set('parent_table_name',$parentCustomTable['CustomTable']['table_name']);
				}
			}else{
				
			}
		}
	}

	public function dir_size() {
		$folder = new Folder(WWW_ROOT . DS . 'files' . DS . $this->Session->read('User.company_id'));
		$size = ($folder->dirsize() / 1073741824);		
		$dbsize = $this->_get_db_size();
		$this->set('dbsize',($dbsize[0][0]['Bytes']/100000000));
		$this->set('appsize', $size);
		$this->render('/Elements/dirsize');
	}

	public function _get_db_size(){
		$this->loadModel('UsageDetail');
		$dataSource = ConnectionManager::getDataSource('default');
		$dbname = $dataSource->config['database'];
		$sql = "
		SELECT
		table_schema AS $dbname,
		ROUND(SUM(data_length + index_length)) AS 'Bytes'
		FROM
		information_schema.tables
		WHERE
		table_schema = '".$dbname."'
		GROUP BY
		table_schema;

		";
		$dbsize = $this->UsageDetail->query($sql);
		return $dbsize[0][0]['Bytes'];
	}

    // ===== M9 access-control corrections 1 to 6 (staging, 26 Oct 2026) =====
    // Refusals and checks are logged to tmp/logs/m9_access.log: time, user, controller/action, page or ajax, method, event,
    // detail. No field values are logged. Administrators (is_mr) are not affected by the guard.
    public function _m9_ac_log($event, $detail = '') {
        $line = implode("\t", array(date('Y-m-d H:i:s'), substr((string)$this->Session->read('User.id'), 0, 8),
            $this->request->params['controller'] . '/' . $this->action, $this->request->is('ajax') ? 'ajax' : 'page',
            $this->request->method(), $event, $detail));
        @file_put_contents(LOGS . 'm9_access.log', $line . "\n", FILE_APPEND | LOCK_EX);
    }

    // A refused AJAX request gets HTTP 403 "Not authorized"; a refused page goes to the access-denied page.
    public function _m9_ac_refuse($event, $detail = '') {
        $this->_m9_ac_log('refused_' . $event, $detail);
        if ($this->request->is('ajax')) {
            $this->response->statusCode(403);
            $this->response->body('Not authorized');
            $this->response->send();
            $this->_stop();
        }
        $this->Session->setFlash(__('You are not authorized to perform this action'), 'default', array('class' => 'alert alert-danger'));
        $this->redirect(array('controller' => 'users', 'action' => 'access_denied', $this->action, 'm9'));
    }

    // Correction 4: may the logged-in user see this document? Exact membership in the JSON share lists (no substring
    // matching), scope lists combined by the document's AND/OR setting, the document's own preparer, approver or issuer,
    // or an approval on this document addressed to or sent by the user.
    public function _m9_can_view_document($id) {
        if ($this->Session->read('User.is_mr') == 1) return true;
        if (empty($id) || is_array($id)) return false;
        $Q = ClassRegistry::init('QcDocument');
        $d = $Q->find('first', array('recursive' => -1, 'conditions' => array('QcDocument.id' => $id), 'fields' => array(
            'QcDocument.id', 'QcDocument.user_id', 'QcDocument.editors', 'QcDocument.branches', 'QcDocument.departments',
            'QcDocument.designations', 'QcDocument.and_or_condition', 'QcDocument.prepared_by', 'QcDocument.approved_by', 'QcDocument.issued_by')));
        if (empty($d)) return false;
        $d = $d['QcDocument'];
        $uid = (string)$this->Session->read('User.id'); $eid = (string)$this->Session->read('User.employee_id');
        $list = function ($v) { $a = json_decode((string)$v, true); return is_array($a) ? array_map('strval', array_filter($a, 'is_scalar')) : array(); };
        if ($uid !== '' && (in_array($uid, $list($d['user_id']), true) || in_array($uid, $list($d['editors']), true))) return true;
        if ($eid !== '' && in_array($eid, array((string)$d['prepared_by'], (string)$d['approved_by'], (string)$d['issued_by']), true)) return true;
        $scope = array();
        foreach (array('branches' => 'User.branch_id', 'departments' => 'User.department_id', 'designations' => 'User.designation_id') as $f => $s) {
            $l = $list($d[$f]);
            if ($l) $scope[] = in_array((string)$this->Session->read($s), $l, true);
        }
        if ($scope) {
            if ($d['and_or_condition'] && false === in_array(false, $scope, true)) return true;
            if (empty($d['and_or_condition']) && in_array(true, $scope, true)) return true;
        }
        $me = array_values(array_filter(array($uid, $eid), 'strlen'));
        if (empty($me)) return false;
        $n = ClassRegistry::init('Approval')->find('count', array('recursive' => -1, 'conditions' => array(
            'Approval.record' => $id, 'Approval.controller_name' => 'qc_documents',
            'OR' => array('Approval.user_id' => $me, 'Approval.from' => $me))));
        return $n > 0;
    }

    // Correction 4 for the document list: the same rule as _m9_can_view_document, as SQL. Ids are reduced to safe
    // characters and matched as whole JSON elements ("id"), so one id can never match inside another.
    public function _m9_share_sql() {
        $k = function ($v) { return preg_replace('/[^A-Za-z0-9_-]/', '', (string)$v); };
        $u = $k($this->Session->read('User.id')); $e = $k($this->Session->read('User.employee_id'));
        $in = function ($col, $v) { return ($v === '') ? '0' : "($col LIKE '%\"$v\"%')"; };
        $set = function ($col) { return "($col IS NOT NULL AND $col NOT IN ('', 'null', '[]'))"; };
        $sc = array(); foreach (array('branches' => 'User.branch_id', 'departments' => 'User.department_id', 'designations' => 'User.designation_id') as $f => $s) {
            $sc[] = array($set("QcDocument.$f"), $in("QcDocument.$f", $k($this->Session->read($s)))); }
        $anySet = '(' . $sc[0][0] . ' OR ' . $sc[1][0] . ' OR ' . $sc[2][0] . ')';
        $andAll = '(' . implode(' AND ', array_map(function ($x) { return '(NOT ' . $x[0] . ' OR ' . $x[1] . ')'; }, $sc)) . ')';
        $orAny = '(' . implode(' OR ', array_map(function ($x) { return '(' . $x[0] . ' AND ' . $x[1] . ')'; }, $sc)) . ')';
        $me = array(); if ($u !== '') $me[] = "'$u'"; if ($e !== '') $me[] = "'$e'"; $meL = $me ? implode(',', $me) : "''";
        $own = ($e === '') ? '0' : "(QcDocument.prepared_by = '$e' OR QcDocument.approved_by = '$e' OR QcDocument.issued_by = '$e')";
        return '(CASE WHEN ' . $in('QcDocument.user_id', $u) . ' OR ' . $in('QcDocument.editors', $u) . ' THEN 1'
            . ' WHEN ' . $own . ' THEN 1'
            . ' WHEN QcDocument.and_or_condition = 1 AND ' . $anySet . ' AND ' . $andAll . ' THEN 1'
            . ' WHEN (QcDocument.and_or_condition = 0 OR QcDocument.and_or_condition IS NULL) AND ' . $orAny . ' THEN 1'
            . " WHEN EXISTS (SELECT 1 FROM approvals m9a WHERE m9a.record = QcDocument.id AND m9a.controller_name = 'qc_documents' AND (m9a.user_id IN ($meL) OR m9a.`from` IN ($meL))) THEN 1"
            . ' ELSE 0 END)';
    }

    // Correction 1b: FlinkISO skipped the controller access check for any request carrying a named qc_document_id
    // (checking only the document, and nothing at all when no such document existed), and skipped the refusal for any
    // page carrying a named custom_table_id. Now an empty or unknown id gets the normal check, and a known document the
    // user may not view is refused.
    public function _m9_named_document_ok() {
        $id = $this->request->params['named']['qc_document_id'];
        if (is_array($id) || (string)$id === '' || (string)$id === '-1') return false;
        if (ClassRegistry::init('QcDocument')->find('count', array('recursive' => -1, 'conditions' => array('QcDocument.id' => $id))) == 0) return false;
        if ($this->_m9_can_view_document($id)) return true;
        $this->_m9_ac_refuse('document_not_shared', substr((string)$id, 0, 8));
    }

    public function _m9_known_custom_table() {
        $id = isset($this->request->params['named']['custom_table_id']) ? $this->request->params['named']['custom_table_id'] : '';
        if (is_array($id) || (string)$id === '' || (string)$id === '-1') return false;
        return ClassRegistry::init('CustomTable')->find('count', array('recursive' => -1, 'conditions' => array('CustomTable.id' => $id))) > 0;
    }

    public function _m9_form_request_ok() {
        // M9 correction 11: only a request to the form's own controller (tbl_..., chd_...), which _m9_access_guard has already
        // checked against that form's lists and its custom_table_id, may skip FlinkISO's refusal.
        return preg_match('/^(tbl|chd)_/', (string)$this->request->params['controller']) === 1 && $this->_m9_known_custom_table();
    }

    // Correction 1: the two document-scoped reads used by the document page (download list, read receipts) are allowed
    // only for a document the user may view. Every other AJAX request now goes through the normal access check.
    public function _m9_read_allowed() {
        $c = $this->request->params['controller']; $a = $this->action;
        if (($c == 'document_downloads' && $a == 'index') || ($c == 'histories' && $a == 'read_receipts')) {
            $id = isset($this->request->params['pass'][0]) ? $this->request->params['pass'][0] : null;
            return $id ? $this->_m9_can_view_document($id) : false;
        }
        return false;
    }

    // Corrections 2, 3 and 6, enforced on the server before any action runs, for page and AJAX requests alike.
    public function _m9_access_guard() {
        if ($this->Session->read('User.is_mr') == 1) return;
        $c = $this->request->params['controller']; $a = $this->action; $post = $this->request->is(array('post', 'put'));
        $uid = (string)$this->Session->read('User.id'); $eid = (string)$this->Session->read('User.employee_id');
        $me = array_values(array_filter(array($uid, $eid), 'strlen'));
        $isPublisher = ($this->Session->read('User.is_publisher') == 1);
        // Corrections 2 and 3: user management and access assignment are for administrators only.
        $adminOnly = array(
            'users' => array('add', 'edit', 'delete', 'index', 'user_access', 'copy_access_from', 'install_updates', 'reset_access', 'check_username'),
            'qc_documents' => array('update_access', 'add_bulk', 'purge', 'fix_names', 'define_change_history_table'), // purge: M9 review finding 2; fix_names, define_change_history_table: M9 correction 12
            'custom_tables' => array('update_access', 'updateaccess'),
        );
        if (isset($adminOnly[$c]) && in_array($a, $adminOnly[$c], true)) $this->_m9_ac_refuse('admin_only');
        // Correction 6: a new revision is published immediately, so it needs the publisher role.
        if ($c == 'qc_documents' && $a == 'update_revision' && false === $isPublisher) $this->_m9_ac_refuse('publish_not_publisher');
        // M9 correction 9 (review finding 1, 26 Oct 2026): editing a document, on opening the form and on saving it, needs the
        // editor right FlinkISO applies when opening the form: the document's creator or a user in its editors list. A posted
        // Access value no longer skips this.
        if ($c == 'qc_documents' && $a == 'edit') {
            $id = isset($this->request->params['pass'][0]) ? $this->request->params['pass'][0] : null;
            $d = (is_scalar($id) && (string)$id !== '') ? ClassRegistry::init('QcDocument')->find('first', array('recursive' => -1, 'fields' => array('QcDocument.id', 'QcDocument.created_by', 'QcDocument.editors'), 'conditions' => array('QcDocument.id' => $id))) : null;
            if ($d) {
                $ed = json_decode((string)$d['QcDocument']['editors'], true); $ed = is_array($ed) ? array_map('strval', array_filter($ed, 'is_scalar')) : array();
                if ((string)$d['QcDocument']['created_by'] !== $uid && false === in_array($uid, $ed, true)) $this->_m9_ac_refuse('not_document_editor', substr((string)$id, 0, 8));
            }
        }
        // M9 correction 11 (review finding 4, 26 Oct 2026): every generated form controller (tbl_..., chd_...), every action,
        // page or AJAX, is checked against that form's own lists: creators to add, viewers to read, editors to change. A child
        // form without lists of its own uses its parent's. A custom_table_id of another form is refused.
        if (preg_match('/^(tbl|chd)_/', (string)$c) === 1) {
            $tbl = null; try { $mc = $this->modelClass; $this->loadModel($mc); $tbl = $this->{$mc}->useTable; } catch (Exception $e) { $tbl = null; }
            $CT = ClassRegistry::init('CustomTable'); $fl = array('CustomTable.id', 'CustomTable.custom_table_id', 'CustomTable.creators', 'CustomTable.editors', 'CustomTable.viewers');
            $f = $tbl ? $CT->find('first', array('recursive' => -1, 'fields' => $fl, 'conditions' => array('CustomTable.table_name' => $tbl))) : null;
            if (empty($f)) $this->_m9_ac_refuse('form_unknown', (string)$tbl);
            $f = $f['CustomTable']; $pid = (string)$f['custom_table_id']; $lists = $f;
            $dec = function ($v) { $x = json_decode((string)$v, true); return is_array($x) ? array_values(array_map('strval', array_filter($x, 'is_scalar'))) : array(); };
            if ($pid !== '' && $pid !== '-1' && empty($dec($f['creators'])) && empty($dec($f['editors'])) && empty($dec($f['viewers']))) {
                $p = $CT->find('first', array('recursive' => -1, 'fields' => $fl, 'conditions' => array('CustomTable.id' => $pid)));
                if ($p) $lists = $p['CustomTable'];
            }
            $named = isset($this->request->params['named']['custom_table_id']) && is_scalar($this->request->params['named']['custom_table_id']) ? (string)$this->request->params['named']['custom_table_id'] : '';
            if ($named !== '' && $named !== '-1' && $named !== (string)$f['id'] && $named !== $pid) $this->_m9_ac_refuse('form_id_mismatch', substr($named, 0, 8));
            if (false === in_array($a, array('addsignature', 'get_signatures', 'checkpass'), true)) {
                if (in_array($a, array('add', 'add_fields'), true)) $need = 'creators';
                elseif (in_array($a, array('index', 'view', 'json', 'xml', 'advance_search', 'reports', 'check_document'), true)) $need = 'viewers';
                elseif ($post || in_array($a, array('edit', 'delete', 'bulk_delete', 'purge'), true)) $need = 'editors';
                else $need = 'viewers';
                if (false === in_array($uid, $dec($lists[$need]), true)) $this->_m9_ac_refuse('form_not_permitted', $need);
            }
        }
        // M9 correction 12 (client review, 27 Oct 2026): the record a request writes must be the record that was checked.
        if ($c == 'qc_documents') $this->_m9_document_target_guard($a, $post, $me);
        if ($post && preg_match('/^(tbl|chd)_/', (string)$c) === 1) $this->_m9_form_target_guard($a);
        if (false === $post || false === is_array($this->request->data)) return;
        // Correction 6: a posted publish flag is ignored on documents for every non-administrator (documents are published
        // only through the approval workflow) and on other records for anyone without the publisher role.
        $gone = array();
        if ($c == 'qc_documents' || false === $isPublisher) {
            foreach ($this->request->data as $m => $v) {
                if (is_array($v) && isset($v['publish']) && $v['publish'] == 1) { $this->request->data[$m]['publish'] = 0; $gone[] = $m; }
                if ($m === 'Approval' && is_array($v)) {
                    foreach ($v as $am => $av) {
                        if (is_array($av) && isset($av['publish']) && $av['publish'] == 1) { $this->request->data['Approval'][$am]['publish'] = 0; $gone[] = 'Approval.' . $am; }
                    }
                }
            }
        }
        if ($gone) $this->_m9_ac_log('publish_flag_removed', implode(',', $gone));
        // Correction 6 with correction 1: an approval response is accepted only from the addressee of a pending approval,
        // for that approval's own record and the record's current step. A response that publishes needs the publisher role.
        $dt = $this->request->data; $ap = null; $outcome = false; $step = null; $rec = null; $ctl = null; $response = false;
        if ($c == 'approval_comments' && $a == 'add_response') {
            $response = true;
            $ap = isset($dt['approval_id']) ? $dt['approval_id'] : '';
            $step = isset($dt['approval_step_id']) ? $dt['approval_step_id'] : '';
            $outcome = (isset($dt['approval_status']) && $dt['approval_status'] == 1);
        } elseif (isset($dt['ApprovalComment']) && is_array($dt['ApprovalComment']) && isset($dt['ApprovalComment']['approval_id'])) {
            $x = $dt['ApprovalComment']; $ap = $x['approval_id'];
            $rec = isset($x['record']) ? $x['record'] : ''; $ctl = isset($x['controller_name']) ? $x['controller_name'] : '';
            $outcome = (isset($x['user_id']) && $x['user_id'] == -1) && ((isset($x['stauts']) && $x['stauts'] == 1) || (isset($x['status']) && $x['status'] == 1));
        }
        if ($ap === null) return;
        if (is_array($ap) || (string)$ap === '') $this->_m9_ac_refuse('approval_missing');
        // M9 correction 10 (review finding 3, 26 Oct 2026): a posted ApprovalComment id must belong to the approval answered.
        if ($response && isset($dt['id']) && is_scalar($dt['id']) && (string)$dt['id'] !== '' && (string)$dt['id'] !== '-1') {
            $cm = ClassRegistry::init('ApprovalComment')->find('first', array('recursive' => -1, 'fields' => array('ApprovalComment.id', 'ApprovalComment.approval_id'), 'conditions' => array('ApprovalComment.id' => $dt['id'])));
            if (empty($cm) || (string)$cm['ApprovalComment']['approval_id'] !== (string)$ap) $this->_m9_ac_refuse('comment_not_of_approval', substr((string)$dt['id'], 0, 8));
        }
        $A = ClassRegistry::init('Approval')->find('first', array('recursive' => -1, 'conditions' => array('Approval.id' => $ap)));
        if (empty($A)) $this->_m9_ac_refuse('approval_unknown');
        $A = $A['Approval'];
        $isTo = in_array((string)$A['user_id'], $me, true); $isFrom = in_array((string)$A['from'], $me, true);
        if ($A['approval_status'] != 0) $this->_m9_ac_refuse('approval_not_pending', substr((string)$ap, 0, 8));
        if ($response || $outcome) { if (false === $isTo) $this->_m9_ac_refuse('approval_not_addressed', substr((string)$ap, 0, 8)); }
        elseif (false === ($isTo || $isFrom)) $this->_m9_ac_refuse('approval_not_party', substr((string)$ap, 0, 8));
        if ($rec !== null && ((string)$rec !== (string)$A['record'] || (string)$ctl !== (string)$A['controller_name'])) $this->_m9_ac_refuse('approval_record_mismatch', substr((string)$ap, 0, 8));
        $M = ClassRegistry::init($A['model_name']);
        if ($step !== null && $M->hasField('approval_step_id')) {
            $cur = (string)$M->field('approval_step_id', array($A['model_name'] . '.id' => $A['record']));
            if ($cur !== '' && $cur !== (string)$step) $this->_m9_ac_refuse('approval_step_mismatch', substr((string)$ap, 0, 8));
        }
        if ($outcome && false === $isPublisher) {
            $publishes = true;
            if ($step !== null && (string)$step !== '') {
                $S = ClassRegistry::init('ApprovalStep');
                $s = $S->find('first', array('recursive' => -1, 'conditions' => array('ApprovalStep.id' => $step)));
                if ($s) {
                    $next = $S->find('count', array('recursive' => -1, 'conditions' => array(
                        'ApprovalStep.approval_process_id' => $s['ApprovalStep']['approval_process_id'],
                        'ApprovalStep.process_step' => $s['ApprovalStep']['process_step'] + 1)));
                    $publishes = ($s['ApprovalStep']['send_to_publishers'] == true) || ($next == 0);
                }
            }
            if ($publishes) $this->_m9_ac_refuse('publish_not_publisher', substr((string)$ap, 0, 8));
        }
        $this->_m9_ac_log('approval_response_accepted', substr((string)$ap, 0, 8));
    }

    // ===== M9 correction 12 (client review, 27 Oct 2026) =====
    // CakePHP saves the record named by the posted id, not the record named in the address. The editor check of correction 9
    // read the address, so an editor of document A could post the id of document B and write to B. Every document id a request
    // carries must now be the document that was checked, and each action that writes a document checks the right to write it.
    public function _m9_doc_editor($id) {
        if (false === is_scalar($id) || (string)$id === '') return false;
        $d = ClassRegistry::init('QcDocument')->find('first', array('recursive' => -1, 'fields' => array('QcDocument.id', 'QcDocument.created_by', 'QcDocument.editors'), 'conditions' => array('QcDocument.id' => $id)));
        if (empty($d)) return false;
        $ed = json_decode((string)$d['QcDocument']['editors'], true); $ed = is_array($ed) ? array_map('strval', array_filter($ed, 'is_scalar')) : array();
        $uid = (string)$this->Session->read('User.id');
        return $uid !== '' && ((string)$d['QcDocument']['created_by'] === $uid || in_array($uid, $ed, true));
    }

    // A pending approval on this document addressed to the user (or, without an approval id, addressed to or sent by the user).
    public function _m9_doc_pending_party($id, $me, $approvalId = null) {
        if (false === is_scalar($id) || (string)$id === '' || empty($me)) return false;
        $cond = array('Approval.record' => $id, 'Approval.controller_name' => 'qc_documents', 'Approval.approval_status' => 0, 'Approval.soft_delete' => 0);
        if ($approvalId === null) $cond['OR'] = array('Approval.user_id' => $me, 'Approval.from' => $me);
        else { $cond['Approval.id'] = $approvalId; $cond['Approval.user_id'] = $me; }
        return ClassRegistry::init('Approval')->find('count', array('recursive' => -1, 'conditions' => $cond)) > 0;
    }

    public function _m9_document_target_guard($a, $post, $me) {
        $pass = isset($this->request->params['pass'][0]) ? $this->request->params['pass'][0] : '';
        if (false === is_scalar($pass)) $this->_m9_ac_refuse('record_id_invalid', 'address');
        $pass = trim((string)$pass);
        // rename changes a document's title, number and revision: editors only, when the form opens and when it is saved.
        if ($a == 'rename' && false === $this->_m9_doc_editor($pass)) $this->_m9_ac_refuse('not_document_editor', substr($pass, 0, 8));
        if (false === $post) return;
        $dt = is_array($this->request->data) ? $this->request->data : array();
        $posted = array();
        foreach (array('QcDocument.id' => array('QcDocument', 'id'), 'Approval.QcDocument.id' => array('Approval', 'QcDocument', 'id'), 'Approval.QcDocument.record' => array('Approval', 'QcDocument', 'record')) as $where => $path) {
            $v = $dt; foreach ($path as $k) $v = (is_array($v) && array_key_exists($k, $v)) ? $v[$k] : null;
            if ($v === null) continue;
            if (false === is_scalar($v)) $this->_m9_ac_refuse('record_id_invalid', $where);
            $v = trim((string)$v); if ($v !== '' && $v !== '-1') $posted[$where] = $v;
        }
        $ids = array_values(array_unique($posted));
        // A new document must not carry the id of an existing one (the save would overwrite it).
        if ($a == 'add') {
            if ($ids && ClassRegistry::init('QcDocument')->find('count', array('recursive' => -1, 'conditions' => array('QcDocument.id' => $ids))) > 0) $this->_m9_ac_refuse('record_id_on_add', substr($ids[0], 0, 8));
            return;
        }
        // With a document in the address, every posted document id must be that document. Without one, at most one document.
        if ($pass !== '') {
            foreach ($posted as $where => $v) if ($v !== $pass) $this->_m9_ac_refuse('record_id_mismatch', $where . ':' . substr($v, 0, 8));
            $t = $pass;
        } else {
            if (count($ids) > 1) $this->_m9_ac_refuse('record_id_mismatch', implode(',', array_keys($posted)));
            $t = $ids ? $ids[0] : '';
        }
        $sends = false;
        foreach (array(isset($dt['Approval']['user_id']) ? $dt['Approval']['user_id'] : null, isset($dt['Approval']['QcDocument']['user_id']) ? $dt['Approval']['QcDocument']['user_id'] : null) as $v)
            foreach ((array)$v as $x) if (is_scalar($x) && trim((string)$x) !== '' && (string)$x !== '-1') $sends = true;
        if ($a == 'approve') {
            // approve saves the posted document: its editors, or the addressee of the pending approval named in the address.
            $apid = (isset($this->request->params['pass'][1]) && is_scalar($this->request->params['pass'][1])) ? (string)$this->request->params['pass'][1] : '';
            if (false === $this->_m9_doc_editor($t) && false === ($apid !== '' && $this->_m9_doc_pending_party($t, $me, $apid))) $this->_m9_ac_refuse('not_document_editor', substr($t, 0, 8));
        } elseif ($a == 'upload_qc_document') {
            // upload_qc_document replaces the file of the posted document: editors only.
            if (false === $this->_m9_doc_editor($t)) $this->_m9_ac_refuse('not_document_editor', substr($t, 0, 8));
        } elseif (($a == 'view' || $a == 'view_archived') && $sends) {
            // Sending for approval from a document page locks the document: its editors, or a party to a pending approval on it.
            if (false === $this->_m9_doc_editor($t) && false === $this->_m9_doc_pending_party($t, $me)) $this->_m9_ac_refuse('not_document_editor_or_party', substr($t, 0, 8));
        } elseif ($pass === '' && $t !== '' && false === in_array($a, array('delete', 'delete_document', 'update_revision', 'view', 'view_archived'), true)) {
            // Any other request naming a document only in its posted data: editors only. (delete and delete_document check the
            // document's editors and creator themselves; update_revision needs the publisher role, correction 6.)
            if (false === $this->_m9_doc_editor($t)) $this->_m9_ac_refuse('not_document_editor', substr($t, 0, 8));
        }
    }

    // Generated forms (tbl_..., chd_...): the form's own lists are checked by correction 11. Here the record itself: a posted id must
    // be the record in the address (none on add), an attached file may come only from the upload area or from this record, and an
    // existing child row may be changed only through its own parent.
    public function _m9_form_target_guard($a) {
        $mc = $this->modelClass; if (false === isset($this->{$mc}) || false === is_object($this->{$mc})) $this->loadModel($mc);
        $M = $this->{$mc}; $dt = $this->request->data;
        if (false === is_array($dt) || empty($dt[$mc]) || false === is_array($dt[$mc])) return;
        $pass = isset($this->request->params['pass'][0]) ? $this->request->params['pass'][0] : '';
        if (false === is_scalar($pass)) $this->_m9_ac_refuse('record_id_invalid', 'address');
        $pass = trim((string)$pass); $isAdd = in_array($a, array('add', 'add_fields'), true);
        if (array_key_exists('id', $dt[$mc])) {
            if (false === is_scalar($dt[$mc]['id'])) $this->_m9_ac_refuse('record_id_invalid', $mc . '.id');
            $id = trim((string)$dt[$mc]['id']);
            if ($id !== '' && $id !== '-1') {
                if ($isAdd) { if ($M->find('count', array('recursive' => -1, 'conditions' => array($mc . '.id' => $id))) > 0) $this->_m9_ac_refuse('record_id_on_add', substr($id, 0, 8)); }
                elseif ($id !== $pass) $this->_m9_ac_refuse('record_id_mismatch', $mc . '.id:' . substr($id, 0, 8));
            }
        }
        $target = $isAdd ? '' : $pass;
        if (isset($dt[$mc]['file_id']) && is_scalar($dt[$mc]['file_id']) && trim((string)$dt[$mc]['file_id']) !== '') {
            $F = ClassRegistry::init('File'); $r = $F->find('first', array('recursive' => -1, 'fields' => array('File.id', 'File.record_id'), 'conditions' => array('File.id' => $dt[$mc]['file_id'])));
            $rid = $r ? (string)$r['File']['record_id'] : '';
            if ($r && $rid !== '' && strpos($rid, 'tmp') !== 0 && $rid !== $target) $this->_m9_ac_refuse('file_of_other_record', substr((string)$dt[$mc]['file_id'], 0, 8));
        }
        foreach ((array)$M->hasMany as $alias => $cfg) {
            if (empty($dt[$alias]) || false === is_array($dt[$alias])) continue;
            $C = ClassRegistry::init(is_array($cfg) && !empty($cfg['className']) ? $cfg['className'] : $alias);
            if (false === $C->hasField('parent_id')) continue;
            foreach ($dt[$alias] as $row) {
                if (false === is_array($row) || false === isset($row['id']) || false === is_scalar($row['id']) || trim((string)$row['id']) === '') continue;
                $p = $C->field('parent_id', array($C->alias . '.id' => $row['id']));
                if ($p !== false && $p !== null && ($target === '' || (string)$p !== $target)) $this->_m9_ac_refuse('child_of_other_record', $alias . ':' . substr((string)$row['id'], 0, 8));
            }
        }
    }


    // M9 correction 13, revision c (staging, 28 Oct 2026). Replaces revision b after the project owner's review.
    // The OnlyOffice save callbacks run without a session, so FlinkISO's access check never applies to them. A callback is
    // accepted only if all of the following hold, otherwise HTTP 403 and a line in tmp/logs/m9_onlyoffice.log:
    // (1) onlyofficesecret is not empty; (2) a token (Authorization: Bearer, or body "token") is HS256 with a valid signature
    // (constant-time comparison); (3) it carries "exp", not past and at most 3600 s ahead; (4) the token's signed content,
    // without iat/exp/nbf, equals the posted body without "token", field for field and in both directions (key, status, url,
    // changesurl, history, users, actions, forcesavetype and any other field); (5) status is an OnlyOffice callback status;
    // (6) the record is the one this callback address names, in the one table that address writes to (fixed map below),
    // its stored file_key equals the body key, and the company_id in the address (where given) is the record's company;
    // (7) every file URL in the body is on the configured document server's host; (8) the same token has not been accepted
    // before (replay): accepted token hashes are kept in tmp/m9_oo_seen until the token expires. No database change.
    // jwtencode refuses payloads shaped like a callback. Self-contained: no dependency on the access-control patch.
    public function _m9_onlyoffice_guard() {
        $cb = array('save_doc', 'save_file', 'save_custom_docs', 'save_template', 'save_rec_doc', 'save_custom_table_doc');
        if (!in_array($this->action, $cb, true)) return;
        $why = $this->_m9_oo_check();
        if ($why !== '') $this->_m9_oo_refuse($why);
    }

    public function _m9_oo_check() {
        $secret = (string)Configure::read('onlyofficesecret');
        if ($secret === '') return 'no_secret';
        $body = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($body)) return 'no_body';
        $tok = '';
        $hdr = (string)$this->request->header('Authorization');
        if (stripos($hdr, 'Bearer ') === 0) $tok = trim(substr($hdr, 7));
        if ($tok === '' && isset($body['token']) && is_string($body['token'])) $tok = $body['token'];
        $pl = $this->_m9_jwt_valid($tok, $secret);
        if (!is_array($pl)) return 'bad_token';
        if (!isset($pl['exp']) || !is_numeric($pl['exp']) || (int)$pl['exp'] < time() || (int)$pl['exp'] > time() + 3600) return 'bad_exp';
        $claims = (isset($pl['payload']) && is_array($pl['payload'])) ? $pl['payload'] : $pl;
        unset($claims['iat'], $claims['exp'], $claims['nbf']);
        $posted = $body;
        unset($posted['token']);
        if (!$this->_m9_oo_same($claims, $posted)) return 'token_body_mismatch';
        if (!isset($body['status']) || !in_array((string)$body['status'], array('1', '2', '3', '4', '6', '7'), true)) return 'bad_status';
        if (!isset($body['key']) || !is_string($body['key']) || $body['key'] === '') return 'no_key';
        $assoc = $this->_m9_oo_key_known($body['key']);
        if ($assoc !== '') return $assoc;
        if (in_array((string)$body['status'], array('2', '6'), true) && !isset($body['url'])) return 'no_url'; // M9 correction 20
        $do = $this->_m9_oo_origin((string)Configure::read('OnlyofficePath'));
        foreach (array('url', 'changesurl') as $k) {
            if (isset($body[$k])) {
                $uo = is_string($body[$k]) ? $this->_m9_oo_origin($body[$k]) : '';
                if ($do === '' || $uo !== $do) return 'url_not_document_server';
            }
        }
        return $this->_m9_oo_replay($tok, (int)$pl['exp']);
    }

    // M9 correction 20: scheme://host:port of an http or https address without user information; '' for anything else.
    public function _m9_oo_origin($u) {
        $p = @parse_url($u);
        if (!is_array($p) || empty($p['scheme']) || empty($p['host']) || isset($p['user']) || isset($p['pass'])) return '';
        $s = strtolower($p['scheme']);
        if ($s !== 'http' && $s !== 'https') return '';
        $port = isset($p['port']) ? (int)$p['port'] : ($s === 'https' ? 443 : 80);
        return $s . '://' . strtolower($p['host']) . ':' . $port;
    }

    // Deep, order-independent equality of two decoded JSON values; types must match exactly.
    public function _m9_oo_same($a, $b) {
        if (is_array($a) !== is_array($b)) return false;
        if (!is_array($a)) return $a === $b;
        if (count($a) !== count($b)) return false;
        foreach ($a as $k => $v) {
            if (!array_key_exists($k, $b) || !$this->_m9_oo_same($v, $b[$k])) return false;
        }
        return true;
    }

    public function _m9_jwt_valid($tok, $secret) {
        if (!is_string($tok) || $tok === '' || $secret === '') return false;
        $p = explode('.', $tok);
        if (count($p) !== 3) return false;
        $d = function ($s) { return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true); };
        $h = json_decode((string)$d($p[0]), true);
        $pl = json_decode((string)$d($p[1]), true);
        if (!is_array($h) || !isset($h['alg']) || $h['alg'] !== 'HS256' || !is_array($pl)) return false;
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $p[0] . '.' . $p[1], $secret, true)), '+/', '-_'), '=');
        if (!hash_equals($sig, $p[2])) return false;
        return $pl;
    }

    // Revision c: each callback address writes to exactly one table, named by exactly one parameter. Only that row is checked.
    // Returns '' when the body key is that row's stored file_key (and the address's company_id, if any, is the row's company).
    public function _m9_oo_key_known($key) {
        $n = $this->request->params['named'];
        $ctl = (string)$this->request->params['controller'];
        $act = (string)$this->action;
        $idParam = 'record_id';
        if ($act === 'save_custom_docs') { $table = 'files'; $idParam = 'file_id'; }
        elseif ($act === 'save_rec_doc' || $act === 'save_file') { $table = 'files'; }
        elseif ($act === 'save_doc' && $ctl === 'templates') { $table = 'files'; }
        elseif ($act === 'save_custom_table_doc') { $table = 'custom_tables'; } // M9 correction 16
        elseif ($act === 'save_doc') { $table = isset($n['controller']) ? (string)$n['controller'] : ''; }
        else { return 'callback_without_stored_key'; } // save_template: pdf_templates has no file_key to verify against
        if (!preg_match('/^[a-z0-9_]+$/', $table)) return 'bad_record_table';
        if (!isset($n[$idParam]) || !is_string($n[$idParam]) || $n[$idParam] === '') return 'no_record_named';
        foreach (array_keys($n) as $k) if (in_array($k, array('record_id', 'file_id'), true) && $k !== $idParam) return 'ambiguous_record';
        $pdo = ConnectionManager::getDataSource('default')->getConnection();
        $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name IN ('file_key', 'company_id')");
        $q->execute(array($table));
        if ((int)$q->fetchColumn() !== 2) return 'bad_record_table';
        $q = $pdo->prepare("SELECT file_key, company_id FROM `$table` WHERE id = ? LIMIT 2");
        $q->execute(array($n[$idParam]));
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1) return 'record_not_found';
        if (!is_string($rows[0]['file_key']) || !hash_equals($rows[0]['file_key'], $key)) return 'key_not_of_record';
        if ($idParam === 'record_id' && (!isset($n['company_id']) || !is_string($n['company_id']) || $n['company_id'] === '')) return 'no_company_named'; // M9 correction 20
        if (isset($n['company_id']) && (string)$n['company_id'] !== (string)$rows[0]['company_id']) return 'company_not_of_record';
        // M9 correction 21: a key stored on more than one row cannot identify the record the save belongs to.
        $q = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE file_key = ?");
        $q->execute(array($key));
        if ((int)$q->fetchColumn() !== 1) return 'shared_file_key';
        return '';
    }

    // Replay: a token is accepted once. Its SHA-256 is kept as a file in tmp/m9_oo_seen until it expires.
    public function _m9_oo_replay($tok, $exp) {
        $dir = TMP . 'm9_oo_seen';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return 'replay_store_unavailable';
        foreach ((array)glob($dir . DS . '*') as $old) if (is_file($old) && (int)@file_get_contents($old) < time()) @unlink($old);
        $f = $dir . DS . hash('sha256', $tok);
        $h = @fopen($f, 'x');
        if ($h === false) return is_file($f) ? 'replayed_token' : 'replay_store_unavailable';
        fwrite($h, (string)$exp);
        fclose($h);
        return '';
    }

    public function _m9_oo_callback_shaped($payload) {
        if (!is_array($payload)) return false;
        foreach (array('key', 'status', 'url', 'changesurl', 'payload', 'users', 'actions', 'history') as $k) if (array_key_exists($k, $payload)) return true;
        return false;
    }

    public function _m9_oo_refuse($why) {
        $line = gmdate('Y-m-d\TH:i:s\Z') . "\t" . $this->request->controller . '/' . $this->action . "\t" . $why . "\t" . (string)$this->request->clientIp() . "\n";
        @file_put_contents(TMP . 'logs' . DS . 'm9_onlyoffice.log', $line, FILE_APPEND | LOCK_EX);
        $this->response->statusCode(403);
        $this->response->type('json');
        $this->response->body('{"error":1}');
        $this->response->send();
        $this->_stop();
    }


    // M9 correction 14, revision b: checks for AppController::delete_document. Reasons are logged through _m9_ac_refuse.
    // The File row must exist, belong to the user's company, belong to a record of this screen that exists and is of the
    // user's company, and the posted file name must be the stored attachment's name. Returns '' or a reason.
    public function _m9_file_delete_check($f, $name) {
        if (empty($f['File']['id'])) return 'file_not_found';
        $co = (string)$this->Session->read('User.company_id');
        if ($co === '' || (string)$f['File']['company_id'] !== $co) return 'file_of_other_company';
        $norm = function ($s) { return strtolower(str_replace(array('_', '-'), '', (string)$s)); };
        $mine = array($norm($this->request->controller), $norm($this->modelClass), $norm(Inflector::classify($this->request->controller)));
        if (!in_array($norm($f['File']['controller']), $mine, true) && !in_array($norm($f['File']['model']), $mine, true)) return 'file_of_other_screen';
        if (empty($f['File']['record_id'])) return 'file_without_record';
        $model = Inflector::classify($this->request->controller);
        try { $this->loadModel($model); $r = $this->$model->find('first', array('recursive' => -1, 'conditions' => array($model . '.id' => $f['File']['record_id']))); } catch (Exception $e) { $r = array(); }
        if (empty($r[$model])) return 'file_record_missing';
        if (array_key_exists('company_id', $r[$model]) && (string)$r[$model]['company_id'] !== $co) return 'record_of_other_company';
        if ($name === '' || $name === '.' || $name === '..' || $name !== basename($name)) return 'bad_file_name';
        if (!in_array($name, $this->_m9_file_names($f), true)) return 'file_name_mismatch';
        return '';
    }

    // The names under which FlinkISO stores an attachment: as uploaded (name.type) and as the editor saves it (cleaned name.type).
    public function _m9_file_names($f) {
        $t = (string)$f['File']['file_type'];
        return array_values(array_unique(array((string)$f['File']['name'] . '.' . $t, $this->_clean_table_names((string)$f['File']['name']) . '.' . $t)));
    }
	public function beforeFilter() {
		// M9 correction 17: callback addresses name the editing user as editor:<id>, because the server firewall (rule 240950) refuses
		// any address ending in user:<non-digits>. The save handlers read 'user', so the value is passed on under that name.
		if (isset($this->request->params['named']['editor']) && !isset($this->request->params['named']['user'])) $this->request->params['named']['user'] = $this->request->params['named']['editor'];
		$this->_m9_onlyoffice_guard(); // M9 correction 13: OnlyOffice callbacks need a valid token
		$this->_check_login();
		// M9 correction 18 (X23): server-side check of signed fields on every POST/PUT, as tested on staging on 20 October 2026.
		if ($this->Session->read('User')) $this->_m9_signature_guard();
		// M9 access-control corrections 2, 3 and 6 (staging, 26 Oct 2026): server-side checks before any action runs.
		if ($this->Session->read('User')) $this->_m9_access_guard();
		if($this->Session->read('User')){
			Configure::write("files", WWW_ROOT . 'files' . DS . $this->Session->read('User.company_id'));
			Configure::write("files_url", Router::url('/', true) . 'files/' . $this->Session->read('User.company_id'));
			Configure::write("path", WWW_ROOT . 'files' . DS . $this->Session->read('User.company_id') . DS . $this->request->controller);
			Configure::write("url", Router::url('/', true) . 'files/' . $this->Session->read('User.company_id') . '/' . $this->request->controller);
			Configure::write("common_path", 'files' . DS . $this->Session->read('User.company_id') . DS . $this->request->controller);
			if (false) { // M9 correction 1 (staging, 26 Oct 2026): an AJAX header or a posted skip_access_check no longer bypasses the access check
				// this will allow user access without checking from access.
				// we need to add locks on child table restricting users to see only their records 
				$this->set('allowAccess',array(
					'custom_table_id'=>$this->request->params['named']['custom_table_id'],
					'allow_access_user'=>$this->Session->read('User.id')
				));
			}else{
				$skip = array('approval_comments','approvals','standards','processes');
				$ignore = array('install_updates', 'register','activate', 'send_otp', 'generate_invoice', 'renew', 'invoices', 'check_invoice_date','login', 'logout', 'forgot_password', 'reset_password', 'save_doc','access_denied','dashboard','dir_size','get_password_change_remind','last_updated_record','assigned_tasks','get_signatures','download_file','get_signature','save_signature','profile','upload','onlyofficechk', 'save_template',  'save_rec_doc','save_custom_docs','save_file', 'change_password','check_password_validation','clean_table_names','jwtencode','get_directory_tree','updateaccess','opt_check','json','xml','return_user_list','org_chart','codigo_nc_number','codigo_ncp_number','codigo_m_number','metrologia');
				// M9 approval exemption (staging, 15 Oct 2026): the approval response page and its response action are let through
				// only for an approval addressed to the logged-in user that is still pending. Nothing else is exempted.
				if ($this->request->params['controller'] == 'approval_comments' && in_array($this->action, array('approval_comments', 'add_response'))
					&& !empty($this->request->params['named']['approval_id'])) {
					$this->loadModel('Approval');
					$m9Pending = $this->Approval->find('count', array('recursive' => -1, 'conditions' => array(
						'Approval.id' => $this->request->params['named']['approval_id'],
						'Approval.user_id' => array_filter(array($this->Session->read('User.id'), $this->Session->read('User.employee_id'))),
						'Approval.approval_status' => 0)));
					if ($m9Pending > 0) $ignore[] = $this->action;
				}
				if ($this->_m9_read_allowed()) $ignore[] = $this->action; // M9 correction 1: document-scoped reads, checked per document
				if(!in_array($this->action,$ignore)){
					// $this->Session->setFlash(__('Blocked Action: '. $this->request->action), 'default', array('class' => 'alert alert-danger'));
					$this->_check_access();
				}
			}
			$skip_track_history = array('check_invoice_dateTue','dir_size','advance_search','assigned_tasks','index','jwtencode','field_fetch','check_document','code_input_main');
			if (
				!in_array($this->action, $skip_track_history) &&
				Inflector::Classify($this->name) != 'App' &&
				Inflector::Classify($this->name) != 'CakeError' &&
				$this->request->params['controller'] != 'user_sessions' &&
				$this->request->params['controller'] != 'email_triggers' &&
				$this->request->params['controller'] != 'billing' && 
				$this->request->params['controller'] != 'graph_panels'
			)
			{
				if($this->Session->read('User') && isset($this->request->params) && ($this->request['controller'] != 'file_uploads' && $this->action != 'get_department_employee') && $this->action !='mlfuserlist'){
					$this->_track_history();
				}
			}
		}
	} 

	public function back() {
		$this->redirect($this->referer());
	}
	
	public function _access_redirect($n = null){
		$ignore = array('install_updates', 'register','activate', 'send_otp', 'generate_invoice', 'renew', 'invoices', 'check_invoice_date','login', 'logout', 'forgot_password', 'reset_password', 'save_doc','access_denied','dashboard','dir_size','get_password_change_remind','last_updated_record','assigned_tasks','get_signatures','download_file','get_signature','save_signature','profile','upload','onlyofficechk', 'save_template',  'save_rec_doc','save_custom_docs','save_file', 'change_password','check_password_validation','clean_table_names','jwtencode','get_directory_tree','updateaccess','opt_check','codigo_nc_number','codigo_ncp_number','codigo_m_number','metrologia');
		if(!in_array($this->action,$ignore)
			&& $this->request->controller != 'qc_documents' 			
			&& $this->request->controller != 'custom_tables'
			&& $this->request->controller != 'standards'
			&& $this->request->controller != 'clauses'
		){
			if($this->request->is('ajax') == false){
				if($this->request->controller == 'employees' && $this->action == 'view'){
				}else{
					if($this->_m9_form_request_ok() == false){ // M9 corrections 1b and 11: only a checked request to the form's own controller skips this refusal
						$this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
						$this->redirect(array('controller' => 'users', 'action' => 'access_denied',$this->action,'02',$n));	
					}
					
				}
			} else{
				if ($this->_m9_form_request_ok() == false) $this->_m9_ac_refuse('ajax_denied', (string)$n); // M9 corrections 1 and 11: HTTP 403 and a log line; a checked form request passes
			}
		}
	}
	public function _check_access() {
		$this->_customtableacces();
		// if user is not admin
		if($this->Session->read('User.is_mr') == false){
			//check if its add/edit/view/ & custom table
			if(strpos($this->request->controller,"child") === false){ 
				if(isset($this->request->params['named']['qc_document_id']) && $this->_m9_named_document_ok()){ // M9 correction 1b: only an existing document the user may view
					$this->loadModel('QcDocument');
					$existChek = $this->QcDocument->exists($this->request->params['pass'][0]);
					if($existChek){
						$this->QcDocument->virtualFields = array(
							'srct' => '
			                    CASE
				                    WHEN QcDocument.and_or_condition = true THEN                             
				                        (select count(*) from qc_documents WHERE 
				                            qc_documents.id = QcDocument.id AND
				                            qc_documents.user_id LIKE "%'.$this->Session->read('User.id').'%" OR
				                            qc_documents.editors LIKE "%'.$this->Session->read('User.id').'%"
				                            AND
				                                IF (qc_documents.branches IS NOT NULL OR qc_documents.branches != "null" ,qc_documents.branches LIKE "%'.$this->Session->read('User.branch_id').'%", "") AND
				                                IF (qc_documents.designations IS NOT NULL OR qc_documents.designations != "null" ,qc_documents.designations LIKE "%'.$this->Session->read('User.designation_id').'%", "") AND 
				                                IF (qc_documents.departments IS NOT NULL  OR qc_documents.departments != "null" ,qc_documents.departments LIKE "%'.$this->Session->read('User.department_id').'%", "")                 
				                        )
				                    WHEN QcDocument.and_or_condition = false THEN 
				                        (select count(*) from qc_documents WHERE 
				                            qc_documents.id = QcDocument.id AND
				                            qc_documents.user_id LIKE "%'.$this->Session->read('User.id').'%" OR
				                            qc_documents.editors LIKE "%'.$this->Session->read('User.id').'%"
				                            AND
				                            IF (qc_documents.branches IS NOT NULL OR qc_documents.branches != "null"  ,qc_documents.branches LIKE "%'.$this->Session->read('User.branch_id').'%", "") OR
				                            IF (qc_documents.designations IS NOT NULL OR qc_documents.designations != "null" ,qc_documents.designations LIKE "%'.$this->Session->read('User.designation_id').'%", "") OR 
				                            IF (qc_documents.departments IS NOT NULL  OR qc_documents.departments != "null" ,qc_documents.departments LIKE "%'.$this->Session->read('User.department_id').'%", "") 
				                        )
				                    ELSE "Un"
				                END'			            
						);
						// $conditions = $this->_check_request();
				        if($this->Session->read('User.is_mr') == false){
				            $accessConditions = array(
				                // 'QcDocument.archived !='=>1,
				                // 'QcDocument.parent_document_id '=>-1,
				                'OR'=>array(
				                    'QcDocument.srct >' => 0,
				                    'QcDocument.prepared_by LIKE '=>"%".$this->Session->read('User.employee_id')."%",
				                    'QcDocument.approved_by LIKE '=>"%".$this->Session->read('User.employee_id')."%",
				                )                
				            );
				        }else{
				            $accessConditions = array(
				                'QcDocument.archived !='=>1,
				                'QcDocument.parent_document_id '=>-1,              
				            );
				        }
						
						$sharing = $this->QcDocument->find('count',array(					
							'conditions'=>array(
								'QcDocument.id'=>$this->request->params['named']['qc_document_id'],
								$accessConditions
							)
						));
						if($this->_m9_can_view_document($this->request->params['named']['qc_document_id']) == false){ // M9 correction 4: exact sharing check
							$this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
							$this->redirect(array('controller' => 'users', 'action' => 'access_denied',$this->action));
						}
					}					
				}else{ 
					//check if its qcDocument controller and if yes, check access with param 0
					if($this->request->controller == 'qc_documents' && isset($this->request->params['pass'][0])){
						$this->loadModel('QcDocument');
						$existChek = $this->QcDocument->exists($this->request->params['pass'][0]);
						
						if($existChek){
							$this->QcDocument->virtualFields = array(
							'srct' => '
			                   CASE
			                    WHEN QcDocument.and_or_condition = true THEN                             
			                        (select count(*) from qc_documents WHERE 
			                            qc_documents.id = QcDocument.id AND
			                            qc_documents.user_id LIKE "%'.$this->Session->read('User.id').'%" OR
			                            qc_documents.editors LIKE "%'.$this->Session->read('User.id').'%"
			                            AND
			                                IF (qc_documents.branches IS NOT NULL OR qc_documents.branches != "null" ,qc_documents.branches LIKE "%'.$this->Session->read('User.branch_id').'%", "") AND
			                                IF (qc_documents.designations IS NOT NULL OR qc_documents.designations != "null" ,qc_documents.designations LIKE "%'.$this->Session->read('User.designation_id').'%", "") AND 
			                                IF (qc_documents.departments IS NOT NULL  OR qc_documents.departments != "null" ,qc_documents.departments LIKE "%'.$this->Session->read('User.department_id').'%", "")                 
			                        )
			                    WHEN QcDocument.and_or_condition = false THEN 
			                        (select count(*) from qc_documents WHERE 
			                            qc_documents.id = QcDocument.id AND
			                            qc_documents.user_id LIKE "%'.$this->Session->read('User.id').'%" OR
			                            qc_documents.editors LIKE "%'.$this->Session->read('User.id').'%"
			                            AND
			                            IF (qc_documents.branches IS NOT NULL OR qc_documents.branches != "null"  ,qc_documents.branches LIKE "%'.$this->Session->read('User.branch_id').'%", "") OR
			                            IF (qc_documents.designations IS NOT NULL OR qc_documents.designations != "null" ,qc_documents.designations LIKE "%'.$this->Session->read('User.designation_id').'%", "") OR 
			                            IF (qc_documents.departments IS NOT NULL  OR qc_documents.departments != "null" ,qc_documents.departments LIKE "%'.$this->Session->read('User.department_id').'%", "") 
			                        )
			                    ELSE "Un"
			                END
			            	');
							
						// $conditions = $this->_check_request();
				        if($this->Session->read('User.is_mr') == false){
				        	$accessConditions = array(
				                // 'QcDocument.archived !='=>1,
				                // 'QcDocument.parent_document_id '=>-1,
				                'OR'=>array(
				                    'QcDocument.srct >' => 0,
				                    'QcDocument.prepared_by LIKE '=>"%".$this->Session->read('User.employee_id')."%",
				                    'QcDocument.approved_by LIKE '=>"%".$this->Session->read('User.employee_id')."%",
				                )                
				            );
				        }else{
				            $accessConditions = array(
				                'QcDocument.archived !='=>1,
				                'QcDocument.parent_document_id '=>-1,              
				            );
				        }
						
						$sharing = $this->QcDocument->find('count',array(					
							'conditions'=>array(
								'QcDocument.id'=>$this->request->params['pass'][0],
								$accessConditions
							)
						));
							if($this->_m9_can_view_document($this->request->params['pass'][0]) == false){ // M9 correction 4: exact sharing check
								$this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
								$this->redirect(array('controller' => 'users', 'action' => 'access_denied',$this->action));
							}
						}						
					}else{
						$this->loadModel('User'); // M9 correction 5 (staging, 26 Oct 2026): the user's access is loaded before it is checked
						$m9User = $this->User->find('first', array('recursive' => -1, 'fields' => array('User.id', 'User.user_access'), 'conditions' => array('User.id' => $this->Session->read('User.id'))));
						$access = json_decode($m9User ? (string)$m9User['User']['user_access'] : '', true);
						$access = $access['user_access'];
						if(isset($access) && in_array($this->request->controller,array_keys($access))){
							if($access[$this->request->controller][$this->action] == 1){
								
							}else{
								$this->_access_redirect(2);
							}
						}else{
							// if delete
							// check if the user is creator // admin // hod 
							// if not access denied error
							$model = $this->modelClass;
							$this->loadModel($model); $m9Prep = ($this->action == 'delete' && isset($this->request->params['pass'][0]) && $this->$model->hasField('prepared_by')) ? $this->$model->field('prepared_by', array($model . '.id' => $this->request->params['pass'][0])) : null; // M9 correction 5: stored preparer, not a posted value
							if($m9Prep && $this->action == 'delete'){
								if($m9Prep == $this->Session->read('User.employee_id') || $this->Session->read('User.is_mr') == 1){
									
								}else{
									$this->_access_redirect(3);
								}
							}else{
								$this->_access_redirect(3);
							}
						}
					}
				} 
			}else{

			}
		}else{
			// if MR/ Admin	
		}
	}

	public function _customtableacces(){		
		if(isset($this->request->params['named']['custom_table_id']) && $this->request->params['named']['custom_table_id'] != -1){
			$this->loadModel('CustomTable');
			$customTable = $this->CustomTable->find('first',array(
				'recursive'=>-1,
				'fields'=>array(
					'CustomTable.id',
					'CustomTable.users',
					'CustomTable.creators',
					'CustomTable.editors',
					'CustomTable.viewers',
					'CustomTable.approvers',
				),
				'conditions'=>array('CustomTable.id'=>$this->request->params['named']['custom_table_id'])));
			
			if($customTable){
				switch ($this->request->action) {
					case 'add':
						if($customTable['CustomTable']['creators']){
							if(is_array(json_decode($customTable['CustomTable']['creators'],true)) && !in_array($this->Session->read('User.id'), json_decode($customTable['CustomTable']['creators'],true))){
								$this->_m9_ac_refuse('form_not_permitted'); 
							}
						}else{
							$this->_m9_ac_refuse('form_not_permitted'); 
						}
					break;
					case 'edit':
						if($customTable['CustomTable']['editors']){
							if(is_array(json_decode($customTable['CustomTable']['editors'],true)) && !in_array($this->Session->read('User.id'), json_decode($customTable['CustomTable']['editors'],true))){
								$this->_m9_ac_refuse('form_not_permitted'); 
							}
						}else{
							$this->_m9_ac_refuse('form_not_permitted'); 
						}
					break;
					case 'index':
						if($customTable['CustomTable']['viewers']){
							if(is_array(json_decode($customTable['CustomTable']['viewers'],true)) && !in_array($this->Session->read('User.id'), json_decode($customTable['CustomTable']['viewers'],true))){
								$this->_m9_ac_refuse('form_not_permitted'); 
							}
						}else{
							$this->_m9_ac_refuse('form_not_permitted'); 
						}
					break;
					case 'view':
						if($customTable['CustomTable']['viewers']){
							if(is_array(json_decode($customTable['CustomTable']['viewers'],true)) && !in_array($this->Session->read('User.id'), json_decode($customTable['CustomTable']['viewers'],true))){
								$this->_m9_ac_refuse('form_not_permitted'); 
							}
						}else{
							$this->_m9_ac_refuse('form_not_permitted'); 
						}
					break;
					case 'reports':
						if($customTable['CustomTable']['viewers']){
							if(is_array(json_decode($customTable['CustomTable']['viewers'],true)) && !in_array($this->Session->read('User.id'), json_decode($customTable['CustomTable']['viewers'],true))){
								$this->_m9_ac_refuse('form_not_permitted'); 
							}
						}else{
							$this->_m9_ac_refuse('form_not_permitted'); 
						}
					break;
				}
			}
		}
	}

	public function _check_request() {
		$onlyBranch = null;
		$onlyOwn = null;
		$con1 = null;
		$con2 = null;
		$modelName = $this->modelClass;
		$deptCon = array();
		$pubCon = array();
		
		// check if user/employee is involved departmentwise
		// and if the user is HoD
		
		// ***************** NOTE STARTS **************//
		// There could be multiple department fields //
		// ***************** NOTE ENDS **************//
		

		if($_SESSION['User']['hod'] == 1 && $this->Session->read('User.is_mr' == 0)){
			foreach($this->$modelName->belongsTo as $key => $belongs){
				if($belongs['className'] == 'Department'){
					$department_field = $belongs['foreignKey'];
					$department_field_class = $key;
				}
			}
			if($department_field){
				$schema = $this->$modelName->schema(); 
				// for json run condition 1
				if($schema[$department_field]['type'] == 'text'){
					$deptCon = array('or'=> array($modelName .'.'.$department_field.' LIKE ' => '%'. $this->Session->read('User.department_id').'%'));
				}else{
					$deptCon = array('or'=> array($modelName .'.'.$department_field => $this->Session->read('User.department_id')));
				}
			}else{
				$deptCon = false;
			}			
		}
		if($this->Session->read('User.is_mr') == 0 && $this->request->controller != 'qc_documents' && $this->request->controller != 'custom_tables' && $this->request->controller != 'standards'){ $onlyBranch = array('or'=>array($deptCon,$modelName.'.branchid'=>json_decode($this->Session->read('User.assigned_branches'),false)));
	}else{
		if($deptCon)$onlyBranch = $deptCon;
	}
	if($this->Session->read('User.is_view_all') == 0){
		$onlyOwn = array(
			'OR'=>array(
				$modelName.'.prepared_by'=>$this->Session->read('User.employee_id'),
				$modelName.'.approved_by'=>$this->Session->read('User.employee_id'),
				$modelName.'.created_by'=>$this->Session->read('User.id'),
				$modelName.'.modified_by'=>$this->Session->read('User.id'),
				$deptCon,
			)
		);
	}
	$pubCon = array($modelName.'.publish'=>1);
	if($this->request->params['named'])
	{
		if(isset($this->request->params['named']['published']) && $this->request->params['named']['published'] == 0){
			$pubCon = array(
				'or'=>array(
					$modelName.'.publish' => 0,
					$modelName.'.publish is NULL'
				)				
			);
		}else{
			
		}
		if(isset($this->request->params['named']['published']) && $this->request->params['named']['published']==null)$con1 = null ; else $con1 = $pubCon;
		if(isset($this->request->params['named']['published']))$conditions=array($onlyBranch,$onlyOwn,$con1);
		else $conditions=array($onlyBranch,$onlyOwn,$con1);
	}else{
		$conditions=array($onlyBranch,$onlyOwn,null,$pubCon,$modelName.'.soft_delete'=>0); 
	}
	return array_filter($conditions);	

}
public function _get_count() {
}

public function _track_history($track = null) {
	$this->loadModel('History');
	$this->History->recursive = - 1;
	$this->History->create();
	$history = array();
	$model_name = Inflector::Classify($this->name);
	$history['History']['model_name'] = $model_name;
	$history['History']['controller_name'] = $this->request->params['controller'];
	$history['History']['action'] = $this->request->action;
	$history['History']['record_id'] = isset($this->request->params['pass'][0])? $this->request->params['pass'][0]: NULL;
	// M9 correction 7 (staging, 15 Oct 2026): password and secret values are never written to the history table.
	// The field name is kept and its value replaced, so the record of what was submitted remains.
	// M9 correction 7b (staging, Oct 2026): closes the gaps found in review. A sensitive key is removed whatever its value type
	// (string, list or nested array). JSON held inside any string (including History.pre_post_values copied into post_values)
	// is decoded, cleaned and re-encoded; unparseable JSON that mentions a sensitive key is not stored. Positional URL arguments of
	// password and token actions are not stored. A final text scrub catches escaped, malformed and url-encoded forms.
	// M9 correction 7c (staging, 21 Oct 2026): the broad "setting|remind|last_modified" exemption is replaced by an exact list of
	// non-secret field names; passphrase, pass_phrase and passkey names are sensitive; keys and string values are URL-decoded
	// (up to 4 rounds, so double encoding is covered) before they are judged, and name:value pairs are scrubbed as well as name=value.
	// record_id (copied from the first URL argument above) is cleared when that argument carries a sensitive value.
	// M9 correction 7d (staging, 21 Oct 2026): the text scrub allows whitespace around ':' and '=' and treats '/', '+' and other
	// punctuation as part of the value, so 'password: A/B+C' and 'secret:A/B+C' in free text are removed in full.
	$m9Frag = 'pass[_\-]?(?:word|wd|code|phrase|key)|pwd|secret|token|otp|api[_\-]?key|private[_\-]?key|credential';
	$m9Key = '/pass[_\-]?(?:word|wd|code|phrase|key)|(^|[_\-])pwd($|[_\-])|secret|token|(^|[_\-])otp($|[_\-])|api[_\-]?key|private[_\-]?key|credential/i';
	$m9Allow = array('activate_password_setting', 'pwd_last_modified');
	$m9Dec = function($s) {
		if (!is_string($s)) return $s;
		for ($i = 0; $i < 4 && strpos($s, '%') !== false; $i++) { $d = rawurldecode($s); if ($d === $s) break; $s = $d; }
		return $s;
	};
	$m9Scrub = function($s, $raw = false) use ($m9Frag, $m9Allow) {
		if (!is_string($s) || $s === '') return $s;
		$keep = function($n) use ($m9Allow) { return in_array(strtolower($n), $m9Allow, true); };
		$n = '([A-Za-z0-9_\-]*(?:'.$m9Frag.')[A-Za-z0-9_\-]*)';
		$s = preg_replace_callback('/'.$n.'(\\\\*"\s*:\s*\\\\*")((?:[^"\\\\]|\\\\[^"\\\\])*)/i',
			function($m) use ($keep) { return ($keep($m[1]) || $m[3] === '' || $m[3] === '[removed]' || strpos($m[3], '[removed: ') === 0) ? $m[0] : $m[1].$m[2].'[removed]'; }, $s);
		// 7d: one rule for name=value, name:value, name => value and name := value. Whitespace (and, in JSON-encoded text,
		// escaped line breaks) is allowed around the separator, and an optional closing quote after the name. The value runs
		// to the next whitespace or '&' (or is a whole quoted string), so '/', '+', ':' and other punctuation inside it cannot survive.
		$w = $raw ? '\s*' : '(?:\s|\\\\[nrt])*';
		$v = $raw ? '"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|[^&\s]+' : '(?:[^&"\s\\\\]|\\\\.)+';
		$s = preg_replace_callback('/'.$n.'((?:\\\\*["\'])?'.$w.'(?::=|=>|=|:)'.$w.')('.$v.')/i',
			function($m) use ($keep) { return ($keep($m[1]) || strpos(ltrim($m[3], "\\\\\"'"), '[removed') === 0) ? $m[0] : $m[1].$m[2].'[removed]'; }, $s);
		return $s;
	};
	$redact = function($v, $depth = 0) use (&$redact, $m9Key, $m9Allow, $m9Frag, $m9Dec, $m9Scrub) {
		if (is_string($v)) {
			$t = ltrim($v);
			if ($depth < 8 && $t !== '' && ($t[0] === '{' || $t[0] === '[')) {
				$d = json_decode($t, true);
				if (is_array($d)) return json_encode($redact($d, $depth + 1));
				if (preg_match('/'.$m9Frag.'/i', $m9Dec($t))) return '[removed: unparseable]';
			}
			$c = $m9Scrub($v, true);
			$u = $m9Dec($c);
			if ($u !== $c && preg_match('/'.$m9Frag.'/i', $u)) { $c2 = $m9Scrub($u, true); if ($c2 !== $u) return $c2; }
			return $c;
		}
		if (!is_array($v)) return $v;
		foreach ($v as $k => $x) {
			$kd = is_string($k) ? $m9Dec($k) : $k;
			if (is_string($kd) && preg_match($m9Key, $kd) && !in_array(strtolower($kd), $m9Allow, true)) $v[$k] = '[removed]';
			else $v[$k] = $redact($x, $depth + 1);
		}
		return $v;
	};
	$m9Params = $this->request->params;
	if (stripos((string)$this->request->params['controller'], 'password_setting') === 0
		|| in_array($this->request->action, array('check_password_validation', 'reset_password', 'activate', 'opt_check', 'send_otp'))) {
		if (!empty($m9Params['pass'])) $m9Params['pass'] = array_fill(0, count($m9Params['pass']), '[removed]');
		$history['History']['record_id'] = NULL;
	}
	// 7d rev 2: a '/' inside a sensitive named URL argument (e.g. /secret:A/B+C) splits the value, and the rest arrives as positional
	// arguments. When any named argument has a sensitive name, every positional argument and record_id are therefore not stored.
	if (!empty($m9Params['named']) && is_array($m9Params['named'])) {
		foreach ($m9Params['named'] as $m9nk => $m9nv) {
			$m9nkd = is_string($m9nk) ? $m9Dec($m9nk) : $m9nk;
			if (is_string($m9nkd) && preg_match($m9Key, $m9nkd) && !in_array(strtolower($m9nkd), $m9Allow, true)) {
				if (!empty($m9Params['pass'])) $m9Params['pass'] = array_fill(0, count($m9Params['pass']), '[removed]');
				$history['History']['record_id'] = NULL;
				break;
			}
		}
	}
	if (isset($history['History']['record_id']) && is_string($history['History']['record_id']) && $redact($history['History']['record_id']) !== $history['History']['record_id']) $history['History']['record_id'] = NULL;
	$history['History']['get_values'] = $m9Scrub(json_encode($redact($m9Params)));
	if(isset($this->request->data['History'])){
		$ppv = isset($this->request->data['History']['pre_post_values']) ? $redact($this->request->data['History']['pre_post_values']) : null;
		$history['History']['pre_post_values'] = ($ppv === null) ? null : $m9Scrub(is_array($ppv) ? json_encode($ppv) : (string)$ppv);
	}
	$history['History']['post_values'] = $m9Scrub(json_encode(array(
		$redact($this->request->data)
	)));
	$history['History']['branch_id'] = $this->Session->read('User.branch_id');
	$history['History']['department_id'] = $this->Session->read('User.department_id');
	$history['History']['branchid'] = $this->Session->read('User.branch_id');
	$history['History']['departmentid'] = $this->Session->read('User.department_id');
	$history['History']['publish'] = 1;
	$history['History']['soft_delete'] = 0;
	$history['History']['created_by'] = $this->Session->read('User.id');
	$history['History']['user_session_id'] = $this->Session->read('User.user_session_id');
	try{
		$this->History->save($history);
	}catch(Exception $e){

	}
	try{
		$this->History->save($history); 
	}catch(Exception $e){
		//update usersession .. end time
		$this->History->UserSession->read(null,$this->Session->read('User.user_session_id'));
		$data['UserSession']['end_time'] = date('Y-m-d H:i:s');
		if($this->Session->read('User.id'))$this->History->UserSession->save($data['UserSession'], false);
	}
}

public function _show_approvals() {
	$approvar['show_panel'] = true;
	return $approvar;
}

public function get_approvals() {
	if ($this->action == 'view' || $this->action == 'edit' || $this->action == 'recreate') {
		// $this->autoRender = false;
		
		$model = $this->modelClass;
		$record = $this->request->params['pass'][0];
		$this->loadModel('Approval');
		$adminCon = array();
		if($this->Session->read('User.is_mr') == false){
			$adminCon = array(
				'OR'=>array(
					'Approval.from'=>array($this->Session->read('User.id'),$this->Session->read('User.employee_id')),
					'Approval.user_id'=>array($this->Session->read('User.id'),$this->Session->read('User.employee_id'))
				)
				);
		}else{
			$adminCon = array();			
		}
		
		$approvals = $this->Approval->find('all', array(
			'order'=>array('Approval.modified'=>'DESC', 'Approval.status'=>'DESC'), 
			'conditions' => array('Approval.model_name' => $model, 'Approval.record' => $record)));
		return $approvals;
	}
}

public function get_approval($id = null, $creator = null) {
	$this->loadModel('Approval');
	$approval = $this->Approval->find('first', array('conditions' => array('Approval.id' => $id)));
	$this->set('approval', $approval);
}

public function _get_approver_list($creator = null) {
	
}

public function _save_approvals($record_id = null) {	
	$this->loadModel('Approval');
	// get approval cycle
	$model = $this->modelClass;
	if(!$record_id)$record_id = $this->request->data['Approval'][$model]['id'];
	$this->request->data['Approval'][$model]['record'] = $record_id;
	// generate some record details as well to add to approval for reference
	$model = $this->modelClass;
	$rec = $this->$model->find('first', array('recursive' => - 1, 'conditions' => array($model . '.id' => $this->request->data['Approval'][$model]['id']), 'fields' => array($model . '.id', $model . '.' . $this->$model->displayField)));
	$title = $rec[$model][$this->$model->displayField] . ' from ' . Inflector::humanize($this->request->controller) . ' section.';
	$cycle = $this->Approval->find('first', array('fields' => array('Approval.id', 'Approval.approval_cycle'), 'group' => array('Approval.approval_cycle'), 'order' => array('Approval.approval_cycle' => 'DESC'), 'conditions' => array('Approval.model_name' => $this->request->data[$this->modelClass]['model_name'], 'Approval.record' => $this->request->data[$this->modelClass]['id'])));

	if ($cycle) $cycle_count = count($cycle) + 1;
	else $cycle_count = 1;
	
	if ($this->request->data['Approval'][$model]['user_id']) {
		$approvalusers = $this->request->data['Approval'][$model]['user_id'];		
	}
	
	if ($this->request->data['Approval'][$model]['user_id']) {
		$approvalusers = $this->request->data['Approval'][$model]['user_id'];
		foreach ($approvalusers as $approvaluser) {
			if ($approvaluser != - 1) {
				$approvaldata['Approval']['title'] = $title;
				$approvaldata['Approval']['controller_name'] = $this->request->controller;
				$approvaldata['Approval']['model_name'] = $this->modelClass;
				$approvaldata['Approval']['record'] = $record_id;
				$approvaldata['Approval']['from'] = $this->request->data['Approval'][$model]['from'];
				$approvaldata['Approval']['user_id'] = $approvaluser;
				$approvaldata['Approval']['approval_status'] = 0;
				$approvaldata['Approval']['approval_mode'] = $this->request->data['Approval'][$model]['approval_mode'];
				$approvaldata['Approval']['approval_type'] = $this->request->data['Approval'][$model]['approval_type'];
				$approvaldata['Approval']['comments'] = $this->request->data['Approval'][$model]['comments'];
				$approvaldata['Approval']['approval_mode'] = $this->request->data['Approval'][$model]['approval_mode'];
				$approvaldata['Approval']['approval_step_id'] = $this->request->data['Approval'][$model]['approval_step_id'];
				$approvaldata['Approval']['approval_cycle'] = $cycle_count;
				
				$this->Approval->create();
				if($this->Approval->save($approvaldata,false)){
					$this->Session->setFlash(__('Approval created.'));
				}else{
					$this->Session->setFlash(__('Approval could not be sent.'));
				}
				$this->_sent_approval_email(
					$approvaldata['Approval']['user_id'],
					0,
					$approvaldata['Approval']['comments'], 
					$approvaldata['Approval']['model_name']
				);
				// lock record
				$model = $approvaldata['Approval']['model_name'];
				$this->loadModel($model);
				$rec = $this->$model->find('first', array('conditions' => array($model . '.id' => $this->request->data['Approval'][$model]['record']), 'recursive' => - 1));
				if ($rec) {
					$rec[$model]['record_status'] = 1;
					$rec[$model]['publish'] = 0;
					// $rec[$this->modelClass]['record_status'] = 1;
					$this->$model->create();
					$this->$model->save($rec);
				}
			}
		} 
	}
}

public function _sent_approval_email($to = null,$message = null,$response = null,$model = null){
	$this->loadModel('User');
	$user = $this->User->find('first',array('conditions'=>array('OR'=>array('User.id'=>$to,'User.employee_id'=>$to))));
	if($user){
		if ($user['Employee']['office_email'] != '') {
			$email = $user['Employee']['office_email'];
		} else if ($user['Employee']['personal_email'] != '') {
			$email = $user['Employee']['personal_email'];
		}
	}
	if($message == 1)$subject = 'FlinkISO: Record Approved by '. $this->Session->read('User.name');
	else $subject = 'FlinkISO: Approval from '. $this->Session->read('User.name');
	if ($email) {
		try {
			App::uses('CakeEmail', 'Network/Email');
			$email = $email;
			$EmailConfig = new CakeEmail("fast");
			$EmailConfig->to($email);
			$EmailConfig->subject($subject);
			$EmailConfig->template('approvalRequest');
			$EmailConfig->viewVars(array(
				'message' => $message,
				'url' => $login_url,
				'response' => $response,
				'by' => $this->Session->read('User.name'),
				'mode' => Inflector::humanize($model),
				'to_name'=>$user['Employee']['name']
			));
			$EmailConfig->emailFormat('html');
			$EmailConfig->send();
		}
		catch (Exception $e) {
			$this->Session->setFlash(__('The user has been saved but fail to send email. Please check smtp details.', true), 'smtp'); 
		}
	}
}

	public function _get_approval_comnments($approval_id = null) {		
		// shift this
		$this->loadModel('ApprovalComment');
		$approvalComments = $this->ApprovalComment->find('all', array('order' => array('ApprovalComment.sr_no' => 'DESC'), 'conditions' => array('ApprovalComment.approval_id' => $approval_id)));
		$this->set('approvalComments', $approvalComments);
	}
	
	public function _save_approval_comments() {	
		if ($this->request->data['ApprovalComment']['user_id'] != - 1 && $this->request->data['ApprovalComment']['status'] != 1) {
			$approvaldata['ApprovalComment']['approval_id'] = $this->request->data['ApprovalComment']['approval_id'];
			$approvaldata['ApprovalComment']['controller_name'] = $this->request->data['ApprovalComment']['controller_name'];
			$approvaldata['ApprovalComment']['model_name'] = $this->request->data['ApprovalComment']['model_name'];
			$approvaldata['ApprovalComment']['record'] = $this->request->data['ApprovalComment']['record'];
			$approvaldata['ApprovalComment']['from'] = $this->request->data['ApprovalComment']['from'];
			$approvaldata['ApprovalComment']['user_id'] = $this->request->data['ApprovalComment']['user_id'];
			$approvaldata['ApprovalComment']['approval_status'] = 0;
			$approvaldata['ApprovalComment']['approval_mode'] = $this->request->data['ApprovalComment']['approval_mode'];
			$approvaldata['ApprovalComment']['approval_type'] = $this->request->data['ApprovalComment']['approval_type'];
			$approvaldata['ApprovalComment']['comments'] = $this->request->data['ApprovalComment']['comments'];
			$approvaldata['ApprovalComment']['approval_mode'] = $this->request->data['ApprovalComment']['approval_mode'];
			$approvaldata['ApprovalComment']['approval_cycle'] = $cycle_count;
			$approvaldata['ApprovalComment']['response_status'] = 0;
			$this->loadModel('ApprovalComment');
			$this->ApprovalComment->create();
			$this->ApprovalComment->save($approvaldata);			
			if ($this->request->data['ApprovalComment']['status'] == 1) {
				// update approval record
				$app = $this->ApprovalComment->Approval->find('first', array('recursive' => - 1, 'conditions' => array('Approval.id' => $this->request->data['ApprovalComment']['approval_id'])));
				$app['Approval']['status'] = 1;
				$this->ApprovalComment->Approval->create();
				$this->ApprovalComment->Approval->save($app);
			}
			if ($this->request->data['ApprovalComment']['status'] == 2) {
				// update approval record
				$app = $this->ApprovalComment->Approval->find('first', array('recursive' => - 1, 'conditions' => array('Approval.id' => $this->request->data['ApprovalComment']['approval_id'])));
				$app['Approval']['status'] = 1;
				$this->ApprovalComment->Approval->create();
				$this->ApprovalComment->Approval->save($app);
			}
			if (1 < 0) {
				// lock record
				$model = $approvaldata['Approval']['model_name'];
				$this->loadModel($model);
				$rec = $this->$model->find('first', array('conditions' => array($model . '.id' => $this->request->data['ApprovalComment']['record']), 'recursive' => - 1));
				$rec[$model]['record_status'] = 1;				
				$this->$model->create();
				$this->$model->save($rec);
			}			
			
		} else if ($this->request->data['ApprovalComment']['user_id'] == - 1 && $this->request->data['ApprovalComment']['stauts'] == 1) {
			// this means record is approved
			$approvaldata['ApprovalComment']['approval_id'] = $this->request->data['ApprovalComment']['approval_id'];
			$approvaldata['ApprovalComment']['controller_name'] = $this->request->data['ApprovalComment']['controller_name'];
			$approvaldata['ApprovalComment']['model_name'] = $this->request->data['ApprovalComment']['model_name'];
			$approvaldata['ApprovalComment']['record'] = $this->request->data['ApprovalComment']['record'];
			$approvaldata['ApprovalComment']['from'] = $this->request->data['ApprovalComment']['from'];
			if ($this->request->data['ApprovalComment']['stauts'] == 1) {
				$approvaldata['ApprovalComment']['user_id'] = 'Approved';
				$approvaldata['ApprovalComment']['approval_status'] = $this->request->data['ApprovalComment']['stauts'];
			} else if ($this->request->data['ApprovalComment']['stauts'] == 2) {
				$approvaldata['ApprovalComment']['user_id'] = 'Not Approved';
				$approvaldata['ApprovalComment']['approval_status'] = $this->request->data['ApprovalComment']['stauts'];
			}
			$approvaldata['ApprovalComment']['approval_mode'] = $this->request->data['ApprovalComment']['approval_mode'];
			$approvaldata['ApprovalComment']['approval_type'] = $this->request->data['ApprovalComment']['approval_type'];
			$approvaldata['ApprovalComment']['comments'] = $this->request->data['ApprovalComment']['comments'];
			$approvaldata['ApprovalComment']['approval_mode'] = $this->request->data['ApprovalComment']['approval_mode'];
			$approvaldata['ApprovalComment']['approval_cycle'] = $cycle_count;			
			$this->loadModel('ApprovalComment');
			$this->ApprovalComment->create();
			if ($this->ApprovalComment->save($approvaldata, false)) {
				// first updated approval record
				$appRec = $this->ApprovalComment->Approval->find('first', array('recursive' => - 1, 'conditions' => array('Approval.id' => $this->request->data['ApprovalComment']['approval_id']),));
				$appRec['Approval']['status'] = 1;
				$appRec['Approval']['approved_date_time'] = $appRec['Approval']['modified'] = date('Y-m-d H:i:s');
				$this->ApprovalComment->Approval->create();
				// later add				
				if ($this->ApprovalComment->Approval->save($appRec, false)) {
				} else {
					echo "failed";
					exit;
				}
				// if approved
				// check if all other approvals are approved or pending
				// if all other approvals are approved, mark record as PUBLISHED
				// else no nothing
				// check if all approved
				$this->ApprovalComment->Approval->virtualFields = array('all' => 'select count(*) from approvals where approvals.record LIKE "' . $this->request->data['ApprovalComment']['record'] . '" AND approvals.controller_name LIKE "' . $this->request->data['ApprovalComment']['controller_name'] . '"', 'approved' => 'select count(*) from approvals where approvals.record LIKE "' . $this->request->data['ApprovalComment']['record'] . '" AND approvals.controller_name LIKE "' . $this->request->data['ApprovalComment']['controller_name'] . '" AND approvals.status = 1',);
				$allApprovals = $this->ApprovalComment->Approval->find('first', array('recursive' => - 1, 'fields' => array('Approval.id', 'Approval.controller_name', 'Approval.record', 'Approval.status', 'Approval.all', 'Approval.approved',), 'conditions' => array('Approval.controller_name' => $this->request->data['ApprovalComment']['controller_name'], 'Approval.record' => $this->request->data['ApprovalComment']['record'],)));
				if ($allApprovals['Approval']['all'] == $allApprovals['Approval']['approved']) {
					// this means record is approved by all
					// publish the record
					$model = $approvaldata['ApprovalComment']['model_name'];
					$this->loadModel($model);
					$rec = $this->$model->find('first', array('conditions' => array($model . '.id' => $this->request->data['ApprovalComment']['record']), 'recursive' => - 1));
					$rec[$model]['publish'] = 1;
					// $rec[$this->modelClass]['record_status'] = 1;
					$this->$model->create();
					$this->$model->save($rec);
				} else {
				}
			}
		}
	}

	public function _show_evidence() {
	}

	public function _get_branch_list() {
		$this->loadModel('Branch');
		$PublishedBranchList = $this->Branch->find('list', array('conditions' => array('Branch.soft_delete' => 0, 'Branch.publish' => 1), 'recursive' => - 1));
		$this->set(compact('PublishedBranchList'));
		return ($PublishedBranchList);
	}

	public function _get_department_list() {
		$this->loadModel('Department');
		$PublishedDepartmentList = $this->Department->find('list', array('conditions' => array('Department.soft_delete' => 0, 'Department.publish' => 1), 'recursive' => - 1));
		$this->set(compact('PublishedDepartmentList'));
		return ($PublishedDepartmentList);
	}

	public function _get_employee_list() {
		$this->loadModel('Employee');
		$PublishedEmployeeList = $this->Employee->find('list', array(
			'conditions' => array('Employee.soft_delete' => 0, 'Employee.publish' => 1), 'recursive' => - 1));
		$this->set(compact('PublishedEmployeeList'));
		return ($PublishedEmployeeList);
	}

	public function _get_designation_list() {
		$this->loadModel('Designation');
		$PublishedDesignationList = $this->Designation->find('list', array('conditions' => array('Designation.publish' => 1, 'Designation.soft_delete' => 0), 'recursive' => - 1));
		$this->set(compact('PublishedDesignationList'));
		return ($PublishedDesignationList);
	}

	public function _get_usernames() {
		$this->loadModel('User');
		$users = $this->User->find('all', array('conditions' => array('User.soft_delete' => 0, 'User.publish' => 1), 'fields' => array('User.id', 'User.name', 'User.username')));
		foreach ($users as $user) {
			$employeeUserNames[$user['User']['id']] = $user['User']['name'] . " (" . $user['User']['username'] . ")";
		}
		return ($employeeUserNames);
	}

	public function _get_user_list() {
		$this->loadModel('User');
		$users = $this->User->find('list', array('conditions' => array('User.soft_delete' => 0, 'User.publish' => 1)));
		$this->set('PublishedUserList', $users);
		return ($users);
	}
	
	public function _qc_document_header($id = null) {
		if(!$this->viewVars['document']){
			$this->loadModel('QcDocument');
			$document = $this->QcDocument->find('first', array(
				'recursive'=>0,
				'fields'=>array(
					'QcDocument.id',
					'QcDocument.name',
					'QcDocument.title',
					'QcDocument.file_key',
					'QcDocument.version',
					'QcDocument.file_status',
					'QcDocument.file_type',
					'QcDocument.schedule_id',
					'QcDocument.data_type',
					'QcDocument.data_update_type',
					'QcDocument.add_records',
					'QcDocument.qc_document_category_id',
					'QcDocument.clause_id',
					'QcDocument.standard_id',
					'QcDocument.document_number',
					'QcDocument.issue_number',
					'QcDocument.date_of_issue',
					'QcDocument.date_of_next_issue',
					'QcDocument.effective_from_date',
					'QcDocument.revision_number',
					'QcDocument.update_version',
					'QcDocument.revision_date',
					'QcDocument.document_type',
					'QcDocument.document_status',
					'QcDocument.issued_by',
					'QcDocument.issuing_authority_id',
					'QcDocument.archived',
					'QcDocument.parent_document_id',
					'QcDocument.parent_id',
					'QcDocument.branches',
					'QcDocument.departments',
					'QcDocument.designations',
					'QcDocument.user_id',
					'QcDocument.editors',
					'QcDocument.publish',
					'QcDocument.prepared_by',
					'QcDocument.approved_by',
					'PreparedBy.id',
					'PreparedBy.name',
					'ApprovedBy.id',
					'ApprovedBy.name',
					'IssuedBy.id',
					'IssuedBy.name',
				),
				'conditions' => array('QcDocument.id' => $id)));
			$this->set('document', $document);
		}else{
			$document = $this->viewVars['document'];
		}
		return $document;
	}

	public function _process_header($id = null){
		$this->loadModel('Process');
		$process = $this->Process->find('first',array('conditions'=>array('Process.id'=>$id),'recursive'=>-1,'fields'=>array(
			'Process.id',
			'Process.name',
			'Process.file_name',
			'Process.file_type',
			'Process.file_key',
			'Process.version_keys',
			'Process.custom_table_id',
			'Process.standards',
			'Process.clauses',
		))); 
		$this->set('process', $process); 		
		$standards = $this->Process->Standard->find('list',array('conditions'=>array('Standard.publish'=>1)));
		$this->set('standards', $standards);
		$clauses = $this->Process->Standard->Clause->find('list',array('conditions'=>array('Clause.publish'=>1)));
		$this->set('clauses', $clauses);
		$this->_get_department_list();
		$this->_get_branch_list();
		return $process;
	}

	public function _generate_onlyoffice_key($record_id = null) {
		$filekey = $record_id;
		$stat = date('ymdhis');
		$filekey = $filekey . $stat;		
		if (strlen($filekey) > 20) $filekey = crc32($filekey);
		$filekey = preg_replace("[^0-9-.a-zA-Z_=]", "_", $filekey);
		$filekey = substr($filekey, 0, min(array(strlen($filekey), 20)));
		return $filekey;
	}
	
	public function jwtencode($payload = null) {
		$payload = json_decode(base64_decode($this->request->params['named']['payload']),true);
        if ($this->_m9_oo_callback_shaped($payload)) $this->_m9_oo_refuse('jwtencode_callback_payload'); // M9 correction 13b
		$payload['iat'] = time();
        $payload['exp'] = time() + (60 * 60);
        $token = $this->generateJWT($payload);
        return $token;
	}

	public function generateJWT($payload = null){
		$signing_key = Configure::read('onlyofficesecret');
	    $header = ["alg" => "SHA256", "typ" => "JWT" ];
	    $header = ["alg" => "HS256", "typ" => "JWT"];	    
	    $header = $this->base64_url_encode(json_encode($header));
	    $payload = $this->base64_url_encode(json_encode($payload));
	    $signature = $this->base64_url_encode(hash_hmac('SHA256', "$header.$payload", $signing_key, true));
	    $jwt = $header;
	    $jwt .= ".".$payload;
	    $jwt .= ".".$signature;  
	    return $jwt;
	}

	public function base64_url_encode(String $text){
    	return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
	}

	public function _calculateHash($encPayload = null) {
		$key = $this->_base64UrlEncode(Configure::read('onlyofficesecret'));
		$has = hash_hmac("sha256", $encPayload, $key,false);
		return $has;
	}
	
	public function _base64UrlEncode($str) {
		return str_replace("/", "_", str_replace("+", "-", trim(base64_encode($str), "=")));
	}
	
	public function _base64UrlDecode($payload) {
		$b64 = str_replace("_", "/", str_replace("-", "+", $payload));
		switch (strlen($b64) % 4) {
			case 2:
			$b64 = $b64 . "==";
			break;
			case 3:
			$b64 = $b64 . "=";
			break;
		}
		return base64_decode($b64);
	}
	
	public function _isValidUuid($uuid = null) {
		if (!is_string($uuid) || (preg_match('/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $uuid) !== 1)) {
			return false;
		}
		return true;
	}
	
	public function listFolderFiles($dir = null, $re = null, $x = null) {
		$skiparray = array('.', '..', '.htaccess', '.DS_Store');
		if ($re == 0) $str = '<ul id="myUL">';
		else $str.= '<ul class="nested">';
		$x++;
		foreach (new DirectoryIterator($dir) as $fileInfo) {
			if (!$fileInfo->isDot()) {
				if (!in_array($fileInfo->getFilename(), $skiparray)) {
					if ($fileInfo->isDir()) {
						if ($this->_isValidUuid($fileInfo->getFilename()) == true) {
							$f = 'Folder';
						} else {
							$f = $fileInfo->getFilename();
						}
						$x++;
						$folderenc = base64_encode(Router::url('/', true) . 'files/' . $this->Session->read('User.company_id') . '/' . strstr($dir, $this->request->controller) . '/' . $fileInfo->getFilename());
						$str.= '<li id="fid_' . $x . '"><div class="caret"><i class="fa fa-folder-o"></i> <strong>' . $f.'</strong>';
						$str.= '<a href="javascript:void(0)" onClick=selectfolder("' . $folderenc . '",' . $x . ');> <i class="fa fa-arrow-circle-up pull-right"></i></a>';
						$str.= '<a href="javascript:void(0)" onClick=deletefolder("' . $folderenc . '",' . $x . ');> <i class="fa fa-remove text-danger pull-right"></i></a></div>';
					} else {
						$x++;						
						$file = Router::url('/', true) . 'files/' . $this->Session->read('User.company_id') . '/' . strstr($dir, $this->request->controller) . '/' . $fileInfo->getFilename();
						$str.= '<li id="id_' . $x . '" class="nli"><div class="file"><a href="' . $file . '" target="_blank"><i class="fa fa-file-o"></i> ' . $fileInfo->getFilename() . '</a>';
						$str.= '<a href="javascript:void(0)" onClick=deletefile("' . base64_encode($file) . '",' . $x . ');> <i class="fa fa-remove text-warning pull-right"></i></a></div>';
					}
					if ($fileInfo->isDir()) {
						$str.= $this->listFolderFiles($fileInfo->getPathname(), 1, $x);
						$x++;
					}
					$str.= '</li>';
					$x++;
				}
			}
		}
		$str.= '</ul>';
		return $str;
	}
	
	public function get_directory_tree($id = null) {
		$x = 0;
		$path = Configure::read('path') . DS . $id . DS . 'addtional_documents';
		if (file_exists($path)) {
			$x++;
			$r = $this->listFolderFiles($path, 0, $x);
			$x++;
			return $r;
		}
	}
	
	public function upload_document($id = null) {
		$this->layout = 'ajax';
		if ($this->request->data['id'] && $this->request->data['file'] && $this->request->data['path']) {
			$path = ltrim(rtrim(str_replace(' ', '_', $this->request->data['path'])));
			$path = Configure::read('path') . DS . $this->request->data['id'] . DS . 'addtional_documents' . DS . $path;
			if (file_exists($path)) {
				echo "path exits";
			} else {
				$folder = new Folder($path);
				if ($folder->create($path)) {
					echo "new Folder Created";
				} else {
					echo "folder creation failed";
				}
			}
			move_uploaded_file($this->request['data']['file']['tmp_name'], $path . DS . $this->request['data']['file']['name']);
		}
		$this->set('data', array('id' => $this->request->data['id']));
		$this->render('/Elements/fileuploads');
	}
	
	public function delete_uploaded_file($file = null) {
		$this->autoRender = false;
		$file = base64_decode($file);
		$file = str_replace(Configure::read('url'), Configure::read('path'), $file);
		unlink($file);
		return true;
	}
	
	public function delete_uploaded_folder($folder = null) {
		$this->autoRender = false;
		$folder = base64_decode($folder);
		$folder = str_replace(Configure::read('url'), Configure::read('path'), $folder);
		if (file_exists($folder)) {
			$path = new Folder($folder);
			$path->delete($folder);
		} else {
			echo "Folder not found";
		}
	}

	public function _single_user_check($id = null){
		$this->loadModel('User');
		$user = $this->User->find('first',array('conditions'=>array('User.id'=>$id)));
		if(!empty($user) && $user['User']['sr_no'] == 1){
			return false;
		}
		$user = $this->User->find('count');
		if($user == 1){
			return false;
		}
		return true;
	}
	
	public function delete() {
		if($this->request->controller == 'processes'){
			$options = array('conditions' => array('Process.' . $this->Process->primaryKey => $id));
			$process = $this->Process->find('first', $options);
			if($this->Session->read('User.is_mr') == false && $process['Process']['created_by'] != $this->Session->read('User.id')){
				$this->Session->setFlash(__('You can not delete this process.'));
				$this->redirect(array('action' => 'index', 'process_id' => $this->request->params['named']['process_id'], 'custom_table_id' => $this->request->params['named']['custom_table_id']));
			}
		}
		if ($this->request->controller != 'custom_tables') {
			// block non admin users to delete
			$stopdelete = array('standards','clauses');
			if(in_array($this->request->controller, $stopdelete)){
				if($this->Session->read('User.is_mr') == 0){
		            $this->Session->setFlash(__('You do not have permission to delete ' . $this->request->controller));
		            $this->redirect(array('action' => 'index'));
		        }
			}

			if ($this->request->is('post') || $this->request->is('put')) {
				if($this->request->controller == 'users'){
					$singleUserCheck = $this->_single_user_check($this->request->data['User']['id']);
					if($singleUserCheck == false){
						$this->Session->setFlash(__('You can not delete this user'));
						$this->redirect(array('controller'=>'users', 'action' => 'index'));	
					}else{
						$this->loadModel('User');
						$user = $this->User->read(null,$this->request->data['User']['id']);
						// $this->User->set(array('soft_delete'=>1,'publish'=>0,'status'=>3));
						$user['User']['soft_delete'] = 1;
						$user['User']['publish'] = 0;
						$user['User']['status'] = 3;
						$this->User->save($user,false);
						$this->Session->setFlash(__('User is deleted.'));
						$this->redirect(array('controller'=>'users', 'action' => 'index'));	
					}
				}else{
					$model = $this->modelClass;
					$id = $this->request->data[$model]['id'];
					$record = $this->$model->find('first',array('conditions'=>array($model.'.id'=>$id),'recursive'=>-1));
					$this->_delete_approvals($id, $model);
					$this->_recursive_delete($this->request->data[$model]['id'],$model);					
					$this->redirect(array('action' => 'index','custom_table_id'=>$record[$model]['custom_table_id'],'qc_document_id'=>$record[$model]['qc_document_id']));
				}
			} else {
				$model = $this->modelClass;
				$this->loadModel($model);
				$rec = $this->$model->find('first',array('conditions'=>array($model.'.id'=>$this->request->params['pass'][0]),'fields'=>array($model.'.id',$model.'.prepared_by')));
				$rec = $rec[$model]['prepared_by'];
				$this->set('rec', $rec);
				$this->set('model', $model);
				$this->render('/Elements/delete');
			}
		}
	}

	public function _recursive_delete($id = null, $model = null){
		$this->loadModel('File');
		// get additional files$files
		$files = array();
		$this->loadModel($model);
		$record = $this->$model->find('first',array('conditions'=>array($model.'.id'=>$id),'recursive'=>-1));
		// find other tables
		$this->loadModel('QcDocument');
		$docs = $this->QcDocument->find('all',array('conditions'=>array(
			'QcDocument.parent_document_id'=>$record[$model]['qc_document_id'],
		),					
		'fields'=>array(
			'QcDocument.id',
			'QcDocument.title',
		)
		));
		
		if($docs){
			foreach($docs as $doc){
				if($doc['CustomTable']){
					foreach($doc['CustomTable'] as $cTable){
						$childModel = Inflector::classify($cTable['table_name']);	
						$this->loadModel($childModel);
						$childRecords = $this->$childModel->find('all',array(
							'recursive'=>-1,
							'conditions'=>array($childModel.'.parent_id'=>$id)));
						foreach($childRecords as $childRecord){
							$this->_recursive_delete($childRecord[$childModel]['id'],$childModel);
						}
					}					
				}				
			}
		}
		
		if($record[$model]['additional_files']){
			$files[] = json_decode($record[$model]['additional_files'],true);
			$files[] = $record[$model]['file_id'];
		}
		else $files[] = $record[$model]['file_id'];
		foreach($files as $file){
			if($file){
				//check if file is linked with other records 
				$filecheck = $this->$model->find('count',array('conditions'=>array($model.'.file_id' => $file, $model.'.id !=' =>$id)));
				if($filecheck == 0){
					$this->set('file',$file);
					$this->File->delete($file);						
					$path = Configure::read('files') . DS . 'files' . DS . $file;
					$folderToDelete = new Folder($path);
					$folderToDelete->delete();
				}
			}
		}
		if($this->$model->hasMany){
			foreach($this->$model->hasMany as $childModel => $info){
				$this->loadModel($childModel);
				$childRecs = $this->$childModel->find('all', array('conditions'=>array($childModel.'.parent_id'=>$id)));
				if($childRecs){
					foreach($childRecs as $childRec){
						$childFilesToDelete = $this->File->delete(array('File.model'=>$childModel,'File.record_id'=>$childRec[$childModel]['id']));
						$cfolder = Configure::read("files") . DS . $this->$childModel->useTable . DS . $childRec[$childModel]['id'];
						$cdirToDelete = new Folder($cfolder);
						$cdirToDelete->delete();

						$this->$childModel->delete($childRec[$childModel]['id']);
					}
				}
			}
		}
		$FilesToDelete = $this->File->delete(array('File.model'=>$model,'File.record_id'=>$id));
		$folder = Configure::read("files") . DS . $this->$model->useTable . DS . $id;
		$dirToDelete = new Folder($folder);
		$dirToDelete->delete();		
		$this->$model->delete($id);
	}

	public function _delete_approvals($id = null, $model = null){
		$this->loadModel('Approval');
		$approvalRecords = $this->Approval->find('all',array('conditions'=>array('Approval.model_name'=>$model,'Approval.record'=>$id)));
		if($approvalRecords){
			foreach($approvalRecords as $approvalRecord){
				$this->Approval->ApprovalComment->deleteAll(array('ApprovalComment.approval_id'=>$approvalRecord['Approval']['id']));
			}
			$this->Approval->delete($approvalRecord['Approval']['id']);
		}else{
			return true;
		}
	}

	public function bulk_delete() {
		$model = $this->modelClass;
		if ($this->request->controller != 'custom_tables') {
			if ($this->request->is('post') || $this->request->is('put')) {
				if(!$this->request->data[$model]['password']){ 
					$this->Session->setFlash(__('Enter Password To Continue'));
					$this->loadModel($model);
					$hasManies = $this->$model->hasMany;
					$this->set('hasManies',$hasManies);
					$this->render('/Elements/bulk_delete');
				}else{					
					// delete records after checking passwords
					$this->loadModel('User');
					$this->loadModel('File');
					$user = $this->User->find('first', array('conditions' => array('User.status' => 1, 'User.soft_delete' => 0, 'User.publish' => 1, 'User.username' => $this->Session->read('User.username'))));
					if($user){
						if (trim($user['User']['password']) != trim(Security::hash($this->data[$model]['password'], 'md5', true))) {
							// incorrect password
							$this->Session->setFlash(__('Incorrect Password'));
							$this->loadModel($model);
							$hasManies = $this->$model->hasMany;
							$this->set('hasManies',$hasManies);
							$this->render('/Elements/bulk_delete');
						}else{
							
							$this->loadModel($model);
							$records = json_decode($this->data[$model]['ids']);
							foreach($records as $id){
								if($id != '' && strlen($id) == 36){
									foreach($this->data[$model]['has_many'] as $hasMany){
										$foreignKey = $this->$model->hasMany[$hasMany]['foreignKey'];										
										$this->loadModel($hasMany);										
										$hasManyRecords = $this->$hasMany->find('list',array('recursive'=>-1,'conditions'=>array($hasMany.'.'.$foreignKey => $id)));
										foreach($hasManyRecords as $hasManyRecordid => $hasManyRecord){
											$this->$hasMany->delete($hasManyRecordid);
											// also need to delete files etc
											$childFilesToDelete = $this->File->delete(array('File.model'=>$hasMany,'File.record_id'=>$hasManyRecord));
											$cfolder = Configure::read("files") . DS . $this->$hasMany->useTable . DS . $hasManyRecordid;
											$cdirToDelete = new Folder($cfolder);
											$cdirToDelete->delete();
										}
									}
									// after deleteting everything, delete main record
									$singleUserCheck = $this->_single_user_check();
									if($singleUserCheck == false){
										$this->Session->setFlash(__('You can not delete this user'));
										$this->redirect(array('action' => 'index','custom_table_id'=>$record[$model]['custom_table_id'],'qc_document_id'=>$record[$model]['qc_document_id']));	
									}
									$this->$model->delete($id);
									$FilesToDelete = $this->File->delete(array('File.model'=>$model,'File.record_id'=>$id));

									//files to delete
									$folder = Configure::read("path") . DS . $this->$model->useTable . DS. $id;
									$dirToDelete = new Folder($folder);
									$dirToDelete->delete();

								}
							}
							$this->Session->setFlash(__('Records Deleted'));
							$this->redirect(array(
								'controller'=>$this->request->controller, 
								'action' => 'index',
								'custom_table_id'=>$this->request->params['named']['custom_table_id'],
								'qc_document_id'=>$this->request->params['named']['qc_document_id']));
						}
					}else{
						$this->Session->setFlash(__('Unknown user'));
						$this->render('/Elements/bulk_delete');
					}
				}
			} else {
				$this->render('/Elements/bulk_delete');
			}
		}
	}

	public function download_pdf($id = null) {		
		$path = WWW_ROOT .'files'. DS . 'pdf'. DS . $this->Session->read('User.id');
		$this->loadModel('QcDocument');
		$doc = $this->QcDocument->find('first', array('conditions' => array('QcDocument.id' => $id), 'recursive' => - 1));
		$file_type = $doc['QcDocument']['file_type'];
		$file_name = $doc['QcDocument']['title'];
		$document_number = $doc['QcDocument']['document_number'];
		$document_version = $doc['QcDocument']['revision_number'];
		$file = $document_number . '-' . $file_name . '-' . $document_version;
		$file = $this->_clean_table_names($file);
		$file = $file . '.' . $file_type;
		$pdffilename = 'returndoc.pdf';		
		$url = Configure::read('url') . DS . $id . DS . $file;		
		$url = str_replace('document_downloads','qc_documents',$url);
		$key = $this->_generate_onlyoffice_key($record_id . date('Ymdhis'));
		if($file_type == 'xls' || $file_type == 'xlsx'){
			$arraytopost = array(
				'fileType' => $file_type,
				'key' => $key,
				'outputtype' => 'pdf',
				'region' => 'en-US',
				'url' => $url,
				'async' => false,
				'spreadsheetLayout'=>array(
					'ignorePrintArea'=>true,
					'orientation'=>'portrait',
					'fitToWidth'=> 0,
					'fitToHeight'=> 0,
					'scale'=> 100,
					'headings'=> false,
					'gridLines'=> false,
					'pageSize'=> array(
						'width'=>'210mm',
						'height'=> '297mm'
					),
					'margins'=>array(
						'left'=> '10mm',
						'right'=> '10mm',
						'top'=> '10mm',
						'bottom'=> '5mm'
					)
				)
			);
		}else{
			$arraytopost = array('fileType' => $file_type, 'key' => $key, 'outputtype' => 'pdf', 'region' => 'en-US', 'url' => $url, 'async' => false);
		}
		$curl = curl_init();
		curl_setopt_array($curl, array(CURLOPT_URL => Configure::read('OnlyofficeConversionApi')."/ConvertService.ashx", CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "", CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 30, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => json_encode($arraytopost), CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/json", "postman-token: 0ce594dd-2a09-651f-b56f-0d77b6bf222e"),));
		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		if ($err) {
			echo "cURL Error #:" . $err;
		} else {
			$xmlString = $response;
			$xmlArray = Xml::toArray(Xml::build($xmlString));
			$file = $xmlArray['FileResult']['FileUrl'];			
			$folder = new Folder();
			if ($folder->create($path)) {
			} else {
				echo "Folder creation failed";
				exit;
			}
			$pdffile = $path . DS . $pdffilename;
			if (file_exists($pdffile)) {
				unlink($pdffile);
			}
			if (copy($file, $pdffile)) {
				return $pdffile; 
			} else {
				return false; 
			} 
		}
	}
	
	public function save_custom_docs() {
		$this->autoRender = false;
		$file_id = $this->request->params['named']['file_id'];		
		if (($body_stream = file_get_contents("php://input")) === FALSE) {
			echo "Bad Request";
		}
		$data = json_decode($body_stream, TRUE);		
		if ($data["status"] == 2) {
			$data = json_decode($body_stream, TRUE);
			$this->loadModel('File');
			$local = $this->File->find('first', array('recursive' => - 1, 'conditions' => array('File.id' => $file_id)));
			$path_for_save = WWW_ROOT . 'files' . DS . $local['File']['company_id'] . DS . $local['File']['controller'] . DS . $local['File']['user_id'] . DS . $local['File']['record_id'] . DS . $local['File']['qc_document_id'];
			$this->loadModel('QcDocument');
			$qcdoc = $this->QcDocument->find('first', array('recursive' => - 1, 'conditions' => array('QcDocument.id' => $local['qc_document_id'])));
			$file_for_save = $path_for_save . DS . $local['File']['name'] . '.' . $local['File']['file_type'];			
			$testfolder = new Folder($path_for_save);
			$testfolder->create($path_for_save);
			chmod($path_for_save, 0777);
			chmod($file_for_save, 0777);
			$downloadUri = $data["url"];
			if (file_get_contents($downloadUri) === FALSE) {
				
			} else {
				$new_data = file_get_contents($downloadUri);
				if (file_put_contents($file_for_save, $new_data)) {
					
				} else {
					
				}
			}			
			$key = $this->_generate_onlyoffice_key($record_id . date('Ymdhis'));			
			$file = $local;
			$file['File']['pre_file_id'] = $file['File']['id'];
			unset($file['File']['id']);
			unset($file['File']['sr_no']);
			unset($file['File']['created']);
			unset($file['File']['modified']);
			$file['File']['data_received'] = json_encode($data);
			$file['File']['pre_file_key'] = $file['File']['file_key'];
			$file['File']['file_key'] = $data['key'];
			$file['File']['new_file_key'] = $key;
			$file['File']['file_status'] = 1;
			$file['File']['last_saved'] = date('Y-m-d H:i:s');
			$this->File->create();
			$this->File->save($file);
			// update record key
			if ($local['tmp'] != 'tmp') {
				$model = $local['File']['model'];
				$this->loadModel($model);
				$rec = $this->$model->find('first', array('conditions' => array($model . '.id' => $local['tmp']), 'recursive' => - 1));
				if ($rec) {
					$rec[$model]['file_key'] = $key;
					$this->$model->create();
					$this->$model->save($rec[$model]);
				}
			}
		}
		echo "{\"error\":0}";
	}
	
	public function get_child_select($parent_id = null, $model = null, $field = null) {
		$this->autoRender = false;
		$parent_id = $this->request->params['named']['parent_id'];
		$model = $this->request->params['named']['model'];
		$field = $this->request->params['named']['field'];
		if ($parent_id) {
			$this->loadModel($model);
			$results = $this->$model->find('list', array('conditions' => array($model . '.' . $field => $parent_id)));
			if ($results) {
				$con_str.= '<option value=-1>Select</option>';
				foreach ($results as $key => $value) {
					$con_str.= '<option value=' . $key . '>' . $value . '</option>';
				}
			}
		}
		return $con_str;
		exit;
	}
	
	public function get_field_list($table = null) {
		$this->autoRender = false;
		if ($this->request->params['named']['table'] != - 1) {
			$this->loadModel('CustomTable');
			$customTable = $this->CustomTable->find('first', array('conditions' => array('CustomTable.table_name' => $this->request->params['named']['table']), 'recursive' => - 1));
			$fields = json_decode($customTable['CustomTable']['fields'], true);
			if ($fields) {
				$con_str.= '<option value=-1>Select</option>';
				foreach ($fields as $field) {
					$con_str.= '<option value=' . $field['field_name'] . '>' . $field['field_name'] . '</option>';
				}
			}
		}
		return $con_str;
		exit;
	}

	public function _pre_search(){
		$modelName = $this->modelClass;
		$skiparray = array('clauses');
		if(!in_array($this->request->controller,$skiparray)){
			if(!empty($customTable['CustomTable']['qc_document_id']) && !isset($this->request->params['named']['qc_document_id'])){			
			}else{
				$document = $this->_qc_document_header($this->request->params['named']['qc_document_id']);
			}
			if(!empty($customTable['CustomTable']['process_id']) && !isset($this->request->params['named']['process_id'])){ 				
			}else{ 
				$process = $this->_process_header($this->request->params['named']['process_id']);
			}
		}
	}

	public function quick_search(){
		$model = $this->modelClass;
		$field_condition = array();
		$this->_pre_search();
		$fields = array_keys($this->$model->schema());
		$x = 0;
		$search_keys = array('name','title','document_number','clause','sub-clause','employee_number');
		$src = $this->$model->displayField;
		unset($this->request->params['named']['timestamp']);
		$conditions = $field_condition = array();
		if($this->request->params['named']['search'] != null){
			// $srcs = explode(' ',$this->request->params['named']['search']);			
			// foreach($srcs as $s){
				foreach ($search_keys as $keys) {
					if(in_array($keys, $fields)){
						$field_condition['OR'][] = array('LOWER(REPLACE('.$model.'.'.$keys.', " ","")) LIKE' => '%'.strtolower(str_replace(' ','', $this->request->params['named']['search'])).'%');
					}
				}
			// }
		}		
		unset($this->request->params['named']['search']);
		unset($this->request->params['named']['custom_table_id']);
		unset($this->request->params['named']['qc_document_id']);
		
		foreach($this->request->params['named'] as $newSrc => $newVal){
			if($newVal != -1 && $newVal != 'timestamp' && $newSrc != 'search' && $newSrc != 'strict' && $newSrc != 'sort' && $newSrc != 'page' && $newSrc != 'direction'){
				$field_condition[] = array($model.'.'.$newSrc => $newVal);
			}
		}
		
		if( isset($this->request->params['named']['strict']) && $this->request->params['named']['strict'] == 0){
			if(is_array($conditions) && is_array($field_condition)){
				$conditions = array_merge($conditions,$field_condition);				
			}
			
		}else{
			if(is_array($conditions) && is_array($field_condition)){				
				$conditions = array('OR'=>array_merge($conditions,$field_condition));				
			}			
		}

		if($model == 'QcDocument'){
			$this->QcDocument->virtualFields = array(
				'childDoc'=>'select count(*) from qc_documents where qc_documents.parent_document_id LIKE QcDocument.id'
			);		
		}

		$this->paginate = array('limit'=>25, 'conditions' => $conditions);
		$this->$model->recursive = 0;
		$this->set(Inflector::variable(Inflector::tableize($model)), $this->paginate());
		$this->_commons();
		$this->render('index');
	}

	public function advance_search() {
		if ($this->request->is('post')) {
			$modal = $this->modelClass;
			$this->loadModel($modal);
			foreach ($this->request->data['order'][$modal] as $field_name => $value) {
				if ($value['value'] != - 1) {
					if ($value['value'] == 0) $ord = 'ASC';
					if ($value['value'] == 1) $ord = 'DESC';
					$oderarray[$modal . '.' . $field_name] = $ord;
				}
			}
			foreach ($this->request->data['basic'][$modal] as $field_name => $details) {
				if ($field_name == 'file_types') $field_name = 'file_type';
				if ($field_name == 'tableTypes') $field_name = 'table_type';
				if ($details['value'] != '') {
					switch ($details['oprator']) {
						case '==':
						$condition[] = array($modal . '.' . $field_name => $details['value']);
						break;
						case '!=':
						$condition[] = array($modal . '.' . $field_name . ' !=' => $details['value']);
						break;
						case '>':
						$condition[] = array($modal . '.' . $field_name . ' >' => $details['value']);
						break;
						case '<':
						$condition[] = array($modal . '.' . $field_name . ' <' => $details['value']);
						break;
						case '%*':
						$condition[] = array('LOWER('.$modal . '.' . $field_name . ') LIKE ' => '%' . $details['value']);
						break;
						case '*%':
						$condition[] = array('LOWER('.$modal . '.' . $field_name . ') LIKE ' => $details['value'] . '%');
						break;
						case 'between':
						$dates = explode('-', $details['value']);
						$startdate = date('Y-m-d', strtotime($dates[0]));
						$enddate = date('Y-m-d', strtotime($dates[1]));
						$condition[] = array('DATE(' . $modal . '.' . $field_name . ') BETWEEN ? and ? ' => array($startdate, $enddate));
						break;
						case '%*%':
						$condition[] = array('LOWER('.$modal . '.' . $field_name . ') LIKE ' => '%' . $details['value'] . '%');
						break;
						default:
							# code...
						break;
					}
				}
			}
			foreach ($this->request->data['advance'][$modal] as $field_name => $details) {
				if ($field_name == 'file_types') $field_name = 'file_type';
				if ($field_name == 'tableTypes') $field_name = 'table_type';
				if ($details['value'] != '') {
					switch ($details['oprator']) {
						case '==':
						$condition[] = array($modal . '.' . $field_name => $details['value']);
						break;
						case '!=':
						$condition[] = array($modal . '.' . $field_name . ' !=' => $details['value']);
						break;
						case '>':
						$condition[] = array($modal . '.' . $field_name . ' >' => $details['value']);
						break;
						case '<':
						$condition[] = array($modal . '.' . $field_name . ' <' => $details['value']);
						break;
						case '%*':
						$condition[] = array('LOWER('.$modal . '.' . $field_name . ') LIKE ' => '%' . $details['value']);
						break;
						case '*%':
						$condition[] = array('LOWER('.$modal . '.' . $field_name . ') LIKE ' => $details['value'] . '%');
						break;
						case 'between':
						break;
						case '%*%':
						$condition[] = array('LOWER('.$modal . '.' . $field_name . ') LIKE ' => '%' . $details['value'] . '%');
						break;
						default:
							# code...
						break;
					}
				}
			}
			if ($condition == null) {
				$this->Session->setFlash(__('No records to display! Please select search criteria.'), 'default', array('class' => 'alert alert-danger'));
				$variable = Inflector::variable(Inflector::pluralize($this->modelClass));
				$this->set($variable, false);
			} else {
				$conditions = $this->_check_request();
				$this->paginate = array('order' => $oderarray, 'conditions' => array($condition, $conditions), 'maxLimit' => 500, 'limit' => 500);
				$variable = Inflector::variable(Inflector::pluralize($this->modelClass));
				$this->set($variable, $this->paginate());
			}
			$this->_commons($this->Session->read('User.id'));
			$this->request->data = $this->request->data;
			$this->_pre_search(); 
			$this->render('index');			
		} else {
			$modal = $this->modelClass;
			$this->loadModel($modal);
			$fields = $this->$modal->schema();
			$belongs = $this->$modal->belongsTo;
			$fields_to_unset = array('id', 'sr_no', 'system_table_id', 'company_id', 'modified', 'modified_by', 'branchid', 'departmentid', 'soft_delete', 'record_status', 'status_user_id', 'division_id','master_list_of_format_id', 'login_status', 'password', 'user_access', 'copy_acl_from', 'password_token', 'user_session_id', 'divisionid', 'document_status', 'parent_id', 'work_instructions', 'record', 'file_key', 'version_key', 'file_dir', 'result', 'file_status', 'file_content','version','version_keys','versions','update_custom_table_document','file_type','data_type','add_records','update_version','cr_status','mark_for_cr_update','pdf_footer_id','signature','temp_date_of_issue','temp_effective_from_date','file_id','additional_files','custom_table_id');
			foreach($fields_to_unset as $f){
				unset($fields[$f]);
			}			
			foreach ($belongs as $key => $value) {
				if (in_array($value['foreignKey'], array_keys($fields))) {
					unset($fields[$value['foreignKey']]);
				}
			}
			foreach ($fields as $field_name => $field_type) {
				switch ($field_type['type']) {
					case 'string':
					$src[$field_name] = array('==' => 'Equal To', '!=' => 'Not Equal To', '%*' => 'Starts With', '*%' => 'Ends With', '%*%' => 'Contains Word');
					break;
					case 'date':
					$src[$field_name] = array('==' => 'Equal To', '>' => 'Greater Than', '<' => 'Less Than', 'between' => 'Between');
					break;
					case 'datetime':
					$src[$field_name] = array('==' => 'Equal To', '>' => 'Greater Than', '<' => 'Less Than', 'between' => 'Between');
					break;
					case 'integer':
					if ($field_type['length'] != 1) {
						$src[$field_name] = array('==' => 'Equal To', '!=' => 'Not Equal To', '>' => 'Greater Than', '<' => 'Less Than');
					} else {
						$src[$field_name] = array('==' => 'Equal To', '!=' => 'Not Equal To', '>' => 'Greater Than', '<' => 'Less Than');
					}
					break;
					case 'text':
					$src[$field_name] = array('%*%' => 'Contains Word');
					break;
					default:
						# code...
					break;
				}
			}
			foreach ($belongs as $bkey => $bvalue) {
				if (in_array($bvalue['foreignKey'], array_values($fields_to_unset))) {
					unset($belongs[$bkey]);
				}
			}
			foreach ($belongs as $key => $value) {
				// check belongs model
				// load model and fect list
				// add to result
				$m = $value['className'];
				$this->loadModel($m);
				try{
					$getrecs = $this->$m->find('list', array('conditions' => array($m . '.publish' => 1, $m . '.soft_delete' => 0)));
					$belongsToModels[$key] = array('field_name' => $value['foreignKey'], 'records' => $getrecs);
				}catch(Exception $e){					
				}
			}
			$customArray = $this->$modal->customArray;
			if($this->request->params['named']['custom_table_id']){
				$this->loadModel('CustomTable');
				$table = $this->CustomTable->find('first',array('fields'=>array('CustomTable.id','CustomTable.fields'), 'recursive'=>-1,'conditions'=>array('CustomTable.id'=>$this->request->params['named']['custom_table_id'])));
				$fields = json_decode($table['CustomTable']['fields'],true); 
			}
			if($customArray){
				foreach ($customArray as $key => $value) {
					foreach($fields as $field){
						if($field['data_type'] == 'radio' && $field['field_name'] = Inflector::variable(Inflector::pluralize($key)) ){
							$belongsToModels[Inflector::underscore($key)] = array('field_name' => Inflector::singularize(Inflector::underscore($key)), 'records' => $value); 
						}
					}
				}
			}		
			$this->set('src', $src);
			$this->set('belongsToModels', $belongsToModels);
			$this->set('modal', $modal);
			$this->render('/Elements/advance-search');
		}
	}
	
	public function _get_hasMany() {
		$model = $this->modelClass;
		$this->set('hasMany', $this->$model->hasMany);
	}
	
	public function reports() {
		$unset = array('id', 'sr_no', 'name', 'title', 'number', 'system_table_id', 'company_id', 'created', 'modified', 'publish', 'branchid', 'departmentid', 'soft_delete', 'record_status', 'status_user_id', 'division_id', 'master_list_of_format_id', 'list_of_kpi_ids', 'system_table', 'risk_assesment_id', 'state_id', 'login_status', 'password', 'user_access', 'assigned_branches', 'copy_acl_from', 'password_token', 'parent_id', 'work_instructions', 'record', 'file_key', 'version_key', 'file_dir', 'result', 'file_content');
		$modelName = $this->modelClass;
		$this->loadModel($modelName);
		$txt = array('string', 'text');
		$number = array('boolean', 'integer', 'float');
		$dates = array('date', 'datetime');
		foreach ($this->$modelName->schema() as $field_name => $details) {
			if (in_array($details['type'], $txt) && !in_array($field_name, $unset)) {
				$labelFields[$field_name] = $field_name;
			}
			if (in_array($details['type'], $number) && !in_array($field_name, $unset)) {
				$dataFields[$field_name] = $field_name;
			}
			if (in_array($details['type'], $dates) && !in_array($field_name, $unset)) {
				$datefields[$field_name] = $field_name;
			}
		}
		$belongs = $this->$modelName->belongsTo;
		foreach ($belongs as $className => $details) {
			if (!in_array($field_name, $unset)) {
				$labelFields[$details['foreignKey']] = $className;
			}
			if (in_array($details['type'], $number) && !in_array($field_name, $unset)) {
				$datefields[$field_name] = $field_name;
			}
		}
		foreach ($this->$modelName->customArray as $cArrayKey => $cArrayD) {
			$variables[] = Inflector::variable($cArrayKey);
			$variablePlurals[] = Inflector::pluralize(Inflector::variable($cArrayKey));
		}
		$x = 0;
		foreach ($dataFields as $dataField) {
			if($this->$modelName->customArray && $variables && $variablePlurals){
				if (in_array($dataField, array_keys($this->$modelName->customArray)) || in_array($dataField, $variables) || in_array($dataField, $variablePlurals)) {
					if (in_array($dataField, array_keys($this->$modelName->customArray))) {
						$values = $this->$modelName->customArray[$dataField];
					}
					if (in_array($dataField, $variables)) {
						$values = $variables[$dataField];
					}
					if (in_array($dataField, $variablePlurals)) {
						$values = $variablePlurals[$dataField];
					}
					foreach ($values as $vkey => $value) {
						$pieChartsData[$dataField][$value] = $this->$modelName->find('count', array('conditions' => array($modelName . '.' . $dataField => $vkey)));
					}
				}
			}
		}
		$this->set(array('labelFields' => $labelFields, 'datefields' => $datefields, 'dataFields' => $dataFields, 'resultTypes' => $resultTypes, 'ccount' => 1));
		$i = 0;
		if ($pieChartsData) {
			foreach ($pieChartsData as $pieChartDataKey => $pieChartDataValues) {
				$pieCharts[$pieChartDataKey]['labels'] = array_keys($pieChartDataValues);
				$pieCharts[$pieChartDataKey]['data'] = array_values($pieChartDataValues);
				foreach ($pieChartDataValues as $pieChartDataValue) {
					$pieCharts[$pieChartDataKey]['borderColor'][] = $pieCharts[$pieChartDataKey]['backgroundColor'][] = "#" . str_pad(dechex(rand(0x000000, 0xFFFFFF)), 6, 0, STR_PAD_LEFT);
				}
				$i++;
				$x++;
			}
		}
		$this->set('pieCharts', $pieCharts);
		$employees = $this->_get_employee_list();
		$departments = $this->_get_department_list();
		$branches = $this->_get_branch_list();
		$i = 0;
		$result = $dresult = $bresult = array();
		foreach ($employees as $id => $name) {
			$cnt = $this->$modelName->find('count', array('conditions' => array($modelName . '.prepared_by' => $id)));
			if ($cnt) {
				$result['labels'][$i] = $name;
				$result['data'][$i] = $cnt;
				$result['borderColor'][$i] = $result['backgroundColor'][$i] = "#" . str_pad(dechex(rand(0x000000, 0xFFFFFF)), 6, 0, STR_PAD_LEFT);
				$i++;
			}
		}
		$this->set('employeeDataEntry', $result);
		$i = 0;
		foreach ($departments as $id => $name) {
			$cnt = $this->$modelName->find('count', array('conditions' => array($modelName . '.departmentid' => $id)));
			$dresult['labels'][$i] = $name;
			$dresult['data'][$i] = $cnt;
			$dresult['borderColor'][$i] = $dresult['backgroundColor'][$i] = "#" . str_pad(dechex(rand(0x000000, 0xFFFFFF)), 6, 0, STR_PAD_LEFT);
			$i++;
		}
		$this->set('departmentDataEntry', $dresult);
		$i = 0;
		foreach ($branches as $id => $name) {
			$cnt = $this->$modelName->find('count', array('conditions' => array($modelName . '.branchid' => $id)));
			$bresult['labels'][$i] = $name;
			$bresult['data'][$i] = $cnt;
			$bresult['borderColor'][$i] = $bresult['backgroundColor'][$i] = "#" . str_pad(dechex(rand(0x000000, 0xFFFFFF)), 6, 0, STR_PAD_LEFT);
			$i++;
		}
		$this->set('branchDataEntry', $bresult);
		$this->render('/Elements/reports');
	}

	public function employee_compliance($startDate = null, $endDate = null){

		if($this->request->params['named']['custom_table_id']){
			$this->loadModel('CustomTable');			
			$this->CustomTable->virtualFields = array(
				'schedule' => 'select schedule_id from qc_documents where qc_documents.id LIKE CustomTable.qc_document_id LIMIT 1',
				'data_type' => 'select data_type from qc_documents where qc_documents.id LIKE CustomTable.qc_document_id LIMIT 1',
				'data_update_type' => 'select data_update_type from qc_documents where qc_documents.id LIKE CustomTable.qc_document_id LIMIT 1'
			);

			$customTable = $this->CustomTable->find('first', array(
				'recursive' => -1, 
				'conditions' => array('CustomTable.id' => $this->request->params['named']['custom_table_id'])
			));

			$qcDocument = $this->CustomTable->QcDocument->find('first', array(
				'recursive' => -1, 
				'conditions' => array('QcDocument.id' => $customTable['CustomTable']['qc_document_id'])
			));
			
			$this->loadModel('Schedule');
			$schedules = $this->Schedule->find('list',array('recursive'=>-1,'fields'=>array('Schedule.id','Schedule.name')));
			$schedule = $this->Schedule->find('first',array('conditions'=>array('Schedule.id' => $customTable['CustomTable']['schedule']), 'recursive'=>-1,'fields'=>array('Schedule.id','Schedule.name')));
			$totalCreators = count(json_decode($customTable['CustomTable']['creators'],true));
			$modelName = Inflector::Classify($customTable['CustomTable']['table_name']);
			$this->loadModel($modelName);
			
			if ($this->request->is('post') || $this->request->is('put')) {
				if($this->request->data[$modelName]['date_range']){
					$dates = explode(' - ',$this->request->data[$modelName]['date_range']);
					$startDate = date('Y-m-d',strtotime($dates[0]));
					$endDate = date('Y-m-d',strtotime($dates[1]));
				}
				if(!$startDate)$startDate = date('Y-m-1');
				if(!$endDate)$endDate = date('Y-m-d',strtotime('+1 month',strtotime($startDate)));

				if($customTable['CustomTable']['data_update_type'] == 0){
					$expected = $this->$modelName->find('count',array(
						'conditions'=>array($modelName.'.created BETWEEN ? AND ?'=>array($startDate,$endDate))
						));	
				}else{			
					$actual = $this->$modelName->find('count',array(
						'conditions'=>array($modelName.'.created BETWEEN ? AND ?'=>array($startDate,$endDate))
					));
					$days = date_diff(date_create($endDate),date_create($startDate));
					$days =  $days->days;
					$expected = $totalCreators * $days;
				}

				$results = $this->_data_entry($schedule['Schedule']['name'], $modelName, $startDate, $endDate,json_decode($customTable['CustomTable']['creators']),$customTable['CustomTable']['data_update_type']);
				$this->set('results',$results[0]);
				$this->set('users',$results[1]);

				$this->set(array(
					'sDate'=>date('Y-m-d',strtotime($dates[0])),
					'eDate'=>date('Y-m-d',strtotime($dates[1])),
					)
				);

			}

		}else{
			
		}
		$this->_get_department_list();
		$this->_get_branch_list();
		$this->set('customTable',$customTable);
		$this->set('qcDocument',$qcDocument);
		$this->set('schedules',$schedules);
		$this->render('/Elements/employee_compliance');
	}

	public function _data_entry($schedule = null, $model = null, $startdate = null, $enddate = null,$creators = null,$update_type = null){
		// 0=>'Any user should update a single document for a defined schedule',
		// 1=>'Every user should update a saperate document for a defined schedule',
		// 2=>'Multiple users should update a single document for a defined schedule',		
		
		$branchCon = $departmentCon = array();
		if($this->request->data[$model]['branch_id'] && $this->request->data[$model]['branch_id'] != -1){
			$branchCon = array('User.branch_id'=>$this->request->data[$model]['branch_id']);
		}

		if($this->request->data[$model]['department_id'] && $this->request->data[$model]['department_id'] != -1){
			$departmentCon = array('User.department_id'=>$this->request->data[$model]['department_id']);
		}

		$addcreators = $this->$model->find('list',array( 
			'fields'=>array($model.'.created_by',$model.'.created_by'),
			'group'=>array($model.'.created_by')));
		
		$allusers = array_merge($creators,array_keys($addcreators));
		$this->loadModel('User');
		$users = $this->User->find('list',array('conditions'=>array(
			'or'=>array( $branchCon,$departmentCon),
			'User.id'=>$allusers)));

		
		$this->loadModel($model);
		// get all creators
		$this->$model->virtualFields = array(
			'username'=>'select users.username from users where users.id LIKE '.$model.'.created_by  LIMIT 1'
		);
		
		foreach($users as $userid => $username){
			if($schedule  == 'Daily' || $schedule == 'Weekly' || $schedule  == 'Monthly' || $schedule  == 'None'){
				$newstartdate = $startdate;
				while ($newstartdate <= $enddate) {
					switch ($schedule) {
						case 'Daily':
							if($update_type != 0)$results[date('D d M Y',strtotime($newstartdate))][$username]['expected'] = 1;
							else $results[date('D d M Y',strtotime($newstartdate))][$username]['expected'] = 0;							
							
							$results[date('D d M Y',strtotime($newstartdate))][$username]['actual'] = $this->$model->find('count',array(
								'conditions'=>array( $model.'.created_by'=>$userid, 'DATE('.$model.'.created)'=>$newstartdate)
							));
							$newstartdate = date('Y-m-d',strtotime('+1 day',strtotime($newstartdate)));
						break;
						case 'Weekly':								

								if($update_type != 0)$results[date('W',strtotime($newstartdate))][$username]['expected'] = 1;
								else $results[date('W',strtotime($newstartdate))][$username]['expected'] = 0;							
								
								$results[date('W',strtotime($newstartdate))][$username]['actual'] = $this->$model->find('count',array(
								'conditions'=>array( 
										$model.'.created_by'=>$userid, 
										'WEEK('.$model.'.created) '=> date('W',strtotime($newstartdate)),
										'YEAR('.$model.'.created) '=> date('Y',strtotime($newstartdate)),
									)
							));
							$newstartdate = date('Y-m-d',strtotime('+1 week',strtotime($newstartdate)));
						break;
						case 'Monthly':
							if($update_type != 0)$results[date('m',strtotime($newstartdate))][$username]['expected'] = 1;
							else $results[date('m',strtotime($newstartdate))][$username]['expected'] = 0;

							$results[date('m',strtotime($newstartdate))][$username]['actual'] = $this->$model->find('count',array(
								'conditions'=>array( 
										$model.'.created_by'=>$userid, 
										'MONTH('.$model.'.created) '=> date('m',strtotime($newstartdate)),
										'YEAR('.$model.'.created) '=> date('Y',strtotime($newstartdate)),
									)
							));
							$newstartdate = date('Y-m-d',strtotime('+1 month',strtotime($newstartdate)));
						break;
						
						case 'None':						
							if($update_type != 0)$results[date('D d M Y',strtotime($newstartdate))][$username]['expected'] = 1;
							else $results[date('D d M Y',strtotime($newstartdate))][$username]['expected'] = 0;							
							
							$results[date('D d M Y',strtotime($newstartdate))][$username]['actual'] = $this->$model->find('count',array(
								'conditions'=>array(
									$model.'.created_by'=>$userid,									
									'DATE('.$model.'.created)'=> $newstartdate
								)
							));
							$newstartdate = date('Y-m-d',strtotime('+1 day',strtotime($newstartdate)));
						break;

						default:
							$newstartdate = date('Y-m-d',strtotime('+1 month',strtotime($newstartdate)));
						break;
					}				
				}
			}else{
				if($schedule  == 'Quarterly'){
					$newstartdate = date('Y-01-01',strtotime($startdate));
					// $newenddate = date('Y-12-01',strtotime($startdate));
					
					if($update_type != 0)$results[date('Y ',strtotime($newstartdate)).'QTR-1'][$username]['expected'] = 1;
					else $results[date('Y ',strtotime($newstartdate)).'QTR-1'][$username]['expected'] = 0;
					
					$results[date('Y ',strtotime($newstartdate)).'QTR-1'][$username]['actual'] = $this->$model->find('count',array(
						'conditions'=>array( 
								$model.'.created_by'=>$userid, 
								'MONTH('.$model.'.created) BETWEEN ? AND ?'=> array(1,3),
								'YEAR('.$model.'.created) '=> date('Y',strtotime($newstartdate)),
							)
					));

					if($update_type != 0)$results[date('Y ',strtotime($newstartdate)).'QTR-2'][$username]['expected'] = 1;
					else $results[date('Y ',strtotime($newstartdate)). 'QTR-2'][$username]['expected'] = 0;

					$results[date('Y ',strtotime($newstartdate)).'QTR-2'][$username]['actual'] = $this->$model->find('count',array(
						'conditions'=>array( 
								$model.'.created_by'=>$userid, 
								'MONTH('.$model.'.created) BETWEEN ? AND ?'=> array(4,6),
								'YEAR('.$model.'.created) '=> date('Y',strtotime($newstartdate)),
							)
					));

					if($update_type != 0)$results[date('Y ',strtotime($newstartdate)).'QTR-3'][$username]['expected'] = 1;
					else $results[date('Y ',strtotime($newstartdate)).'QTR-3'][$username]['expected'] = 0;

					$results[date('Y ',strtotime($newstartdate)).'QTR-3'][$username]['actual'] = $this->$model->find('count',array(
						'conditions'=>array( 
								$model.'.created_by'=>$userid, 
								'MONTH('.$model.'.created) BETWEEN ? AND ?'=> array(7,9),
								'YEAR('.$model.'.created) '=> date('Y',strtotime($newstartdate)),
							)
					));

					if($update_type != 0)$results[date('Y ',strtotime($newstartdate)).'QTR-4'][$username]['expected'] = 1;
					else $results[date('Y ',strtotime($newstartdate)).'QTR-4'][$username]['expected'] = 0;

					$results[date('Y ',strtotime($newstartdate)).'QTR-4'][$username]['actual'] = $this->$model->find('count',array(
						'conditions'=>array( 
								$model.'.created_by'=>$userid, 
								'MONTH('.$model.'.created) BETWEEN ? AND ?'=> array(10,12),
								'YEAR('.$model.'.created) '=> date('Y',strtotime($newstartdate)),
							)
					));					
				}

				if($schedule  == 'Half-Yearly'){
					$newstartdate = date('Y-01-01',strtotime($startdate));

					if($update_type != 0)$results[date('Y ',strtotime($newstartdate)).'Jan-June'][$username]['expected'] = 1;
					else $results[date('Y ',strtotime($newstartdate)).'Jan-June'][$username]['expected'] = 0;
					
					$results[date('Y ',strtotime($newstartdate)).'Jan-June'][$username]['actual'] = $this->$model->find('count',array(
						'conditions'=>array( 
								$model.'.created_by'=>$userid, 
								'MONTH('.$model.'.created) BETWEEN ? AND ?'=> array(1,6),
								'YEAR('.$model.'.created) '=> date('Y',strtotime($newstartdate)),
							)
					));

					if($update_type != 0)$results[date('Y ',strtotime($newstartdate)).'July-Dec'][$username]['expected'] = 1;
					else $results[date('Y ',strtotime($newstartdate)).'July-Dec'][$username]['expected'] = 0;

					$results[date('Y ',strtotime($newstartdate)).'July-Dec'][$username]['actual'] = $this->$model->find('count',array(
						'conditions'=>array( 
								$model.'.created_by'=>$userid, 
								'MONTH('.$model.'.created) BETWEEN ? AND ?'=> array(7,12),
								'YEAR('.$model.'.created) '=> date('Y',strtotime($newstartdate)),
							)
					));
				}

				if($schedule  == 'Yearly'){
					$newstartdate = date('Y-01-01',strtotime($startdate));

					if($update_type != 0)$results[date('Y',strtotime($newstartdate))][$username]['expected'] = 1;
					else $results[date('Y',strtotime($newstartdate))][$username]['expected'] = 0;

					$results[date('Y',strtotime($startdate))][$username]['actual'] = $this->$model->find('count',array(
						'conditions'=>array( 
								$model.'.created_by'=>$userid, 								
								'YEAR('.$model.'.created) '=> date('Y',strtotime($newstartdate)),
							)
					));					
				}
			}			
		}				
		return array($results,$users);
	}
	
	public function _get_fields($custom_table_id = null) {
		$this->loadModel('CustomTable');
		$fieldTypes = $this->CustomTable->customArray['fieldTypes'];
		$displayTypes = $this->CustomTable->customArray['displayTypes'];
		$table = $this->CustomTable->find('first', array('recursive' => - 1, 'conditions' => array('CustomTable.id' => $custom_table_id)));
		$modelName = Inflector::classify($table['CustomTable']['table_name']);
		if($table['CustomTable']['has_many']){
			$hasMany = array();
			foreach (json_decode($table['CustomTable']['has_many'], true) as $hm) {
				$hasMany[$hm['table_name']] = $hm['friendly_name'];
			}
		}else{
			$hasMany = $this->$modelName->hasMany;
			foreach($hasMany as $key => $value){
				$hasMany[$key] = $value['className'];
			}
		}		
		$belongsTo = array();		
		if($table['CustomTable']['belongs_to']){
			foreach (json_decode($table['CustomTable']['belongs_to'], true) as $key => $value) {
				$belongsTo[$value] = $key;
			}
		}		
		foreach (json_decode($table['CustomTable']['fields'], true) as $fields) {
			if ($fields['linked_to'] != - 1) {
				$linkedFields[Inflector::Classify($fields['field_name']) ] = $fields['field_name'];				
			}
			if (in_array($fields['field_type'], array(2, 3, 4))) {
				$numberFields[$fields['field_name']] = $fields['field_name'];
			}
			if (in_array($fields['field_type'], array(4, 5, 6))) {
				$dateFields[$fields['field_name']] = $fields['field_name'];
			}
		}
		return array($linkedFields, $numberFields, $dateFields, $belongsTo, $hasMany);
	}
	
	public function generate_charts() {
		$allFields = $this->_get_fields($this->request->params['named']['custom_table_id']);
		if ($this->request->params['pass'][0]) $ccount = $this->request->params['pass'][0];
		else $ccount = 1;
		if ($this->request->is('post')) {
			$modelName = $this->modelClass;
			$this->loadModel($modelName);
			$txt = array('string', 'text');
			$number = array('boolean', 'integer', 'float');
			$dates = array('date', 'datetime');
			if (in_array($details['type'], $txt) && !in_array($field_name, $unset)) {
				$labelFields[$field_name] = $field_name;
			}
			if (in_array($details['type'], $number) && !in_array($field_name, $unset)) {
				$dataFields[$field_name] = $field_name;
			}
			if (in_array($details['type'], $dates) && !in_array($field_name, $unset)) {
				$datefields[$field_name] = $field_name;
			}
			$find = $this->request->data['Reports'];
			$find = array_shift($find);			
			$belongs = $this->$modelName->belongsTo;
			if ($find['lables'] && isset($find['result_type'])) {
				// first get label master
				foreach ($belongs as $className => $details) {
					if ($find['lables'] == $className) { // ??
						$lists = $this->$modelName->$className->find('list');
						foreach ($lists as $key => $name) {
							if($find['result_type'] == 0){
								$result[$name] = $this->$modelName->find(								
									'count', array('conditions' => array($modelName . '.' . $details['foreignKey'] => $key)));
							}
							if($find['result_type'] == 1){
								$this->$modelName->virtualFields = array('sumvalue'=>'SUM('.$modelName.'.'.$find['data_field'].')');
								$rec = $this->$modelName->find(
									'first',
									array(
										'fields'=>array($modelName.'.sumvalue',$modelName.'.sumvalue'),
										'recursive'=>-1,
										'limit'=>1,										
										'conditions' => array($modelName . '.' . $details['foreignKey'] => $key)
									));
								if($rec[$modelName]['sumvalue'])$result[$name] = $rec[$modelName]['sumvalue'];
								else $result[$name] = 0;
							}
							if($find['result_type'] == 2){
								$this->$modelName->virtualFields = array('sumvalue'=>'AVG('.$modelName.'.'.$find['data_field'].')');
								$rec = $this->$modelName->find(
									'first',
									array(
										'fields'=>array($modelName.'.sumvalue',$modelName.'.sumvalue'),
										'recursive'=>-1,
										'limit'=>1,										
										'conditions' => array($modelName . '.' . $details['foreignKey'] => $key)
									));
								if($rec[$modelName]['sumvalue'])$result[$name] = $rec[$modelName]['sumvalue'];
								else $result[$name] = 0;
							}
						}
					}
				}
			}
		}

		$i = 0;
		foreach ($result as $key => $r) {
			$formChart['labels'][$i] = $key;
			$formChart['data'][$i] = $r;
			$formChart['borderColor'][$i] = $formChart['backgroundColor'][$i] = "#" . str_pad(dechex(rand(0x000000, 0xFFFFFF)), 6, 0, STR_PAD_LEFT);
			$i++;
		}
		$i = 0;
		$resultTypes = array('count', 'sum', 'avg');
		$chartTypes = array('line', 'bar', 'pie', 'doughnut');
		$this->set(array('labelFields' => $allFields[0], 'datefields' => $allFields[1], 'dataFields' => $allFields[2], 'belongsTo' => $allFields[3], 'hasMany' => $allFields[4], 'resultTypes' => $resultTypes, 'ccount' => $ccount));
		$this->set(array('resultTypes' => $resultTypes, 'chartTypes' => $chartTypes));
		$this->set('formChart', $formChart);
		$this->render('/Elements/generate_charts');
	}

	public function _getDisplayField($model = null){
		echo $model;
		exit;
	}

	public function get_default($model = null){
		$this->autoRender = false;
		$this->loadModel($model);
		return $this->$model->displayField;
	}

	public function _table_menu(){
		$this->loadModel('CustomTable');
		$standards = $this->CustomTable->QcDocument->Standard->find('list',array('conditions'=>array('Standard.publish'=>1)));
		$documentTypes = $this->CustomTable->QcDocument->customArray['documentTypes'];
		foreach($standards as $key => $value){
			foreach($documentTypes as $dkey => $documentType){
				$this->CustomTable->virtualFields = array(
					'srct' => '
	                    CASE
	                        WHEN QcDocument.and_or_condition = true THEN                             
	                            (select count(*) from qc_documents WHERE 
	                                qc_documents.id = QcDocument.id AND
	                                    IF (qc_documents.branches IS NOT NULL OR qc_documents.branches != "null"  ,qc_documents.branches LIKE "%'.$this->Session->read('User.branch_id').'%", "") AND
	                                    IF (qc_documents.designations IS NOT NULL OR qc_documents.designations != "null" ,qc_documents.designations LIKE "%'.$this->Session->read('User.designation_id').'%", "") AND 
	                                    IF (qc_documents.departments IS NOT NULL  OR qc_documents.departments != "null" ,qc_documents.departments LIKE "%'.$this->Session->read('User.department_id').'%", "") 
	                            )
	                        WHEN QcDocument.and_or_condition = false THEN 
	                            (select count(*) from qc_documents WHERE 
	                                qc_documents.id = QcDocument.id AND
	                                IF (qc_documents.branches IS NOT NULL OR qc_documents.branches != "null"  ,qc_documents.branches LIKE "%'.$this->Session->read('User.branch_id').'%", "") OR
	                                IF (qc_documents.designations IS NOT NULL OR qc_documents.designations != "null" ,qc_documents.designations LIKE "%'.$this->Session->read('User.designation_id').'%", "") OR 
	                                IF (qc_documents.departments IS NOT NULL  OR qc_documents.departments != "null" ,qc_documents.departments LIKE "%'.$this->Session->read('User.department_id').'%", "") 
	                            )
	                        ELSE "Un"
	                    END
	            '
				);
				$conditions = $this->_check_request();
				$accessConditions = array();
		        // if($this->Session->read('User.is_mr') == false){
		        //     $accessConditions = array(
		        //         'QcDocument.archived !='=>1,
		        //         'QcDocument.parent_document_id '=>-1,
		        //         'OR'=>array(
		        //             'QcDocument.srct >' => 0,
		        //             'QcDocument.prepared_by LIKE '=>"%".$this->Session->read('User.employee_id')."%",
		        //             'QcDocument.approved_by LIKE '=>"%".$this->Session->read('User.employee_id')."%",
		        //         )                
		        //     );
		        // }else{
		        //     $accessConditions = array(
		        //         'QcDocument.archived !='=>1,
		        //         'QcDocument.parent_document_id '=>-1,              
		        //     );
		        // }

				$result = $this->CustomTable->find('all',array(
					'recursive'=>0,
					'fields'=>array('CustomTable.id','CustomTable.name','CustomTable.table_name','CustomTable.table_version','CustomTable.qc_document_id','CustomTable.process_id','CustomTable.srct'), 
					'conditions'=>array(
						'QcDocument.standard_id'=>$key,
						'CustomTable.publish' => 1,
						'QcDocument.document_type'=>$dkey,
						'QcDocument.parent_document_id'=> -1,
						'CustomTable.table_locked' => 0,
						'CustomTable.table_name NOT LIKE' => '%_child_%',
						'OR' => array(
							'QcDocument.departments LIKE ' => '%' . $this->Session->read('User.department_id') . '%',
							'QcDocument.branches LIKE ' => '%' . $this->Session->read('User.branch_id') . '%',
							'QcDocument.user_id LIKE ' => '%' . $this->Session->read('User.id') . '%',
							'QcDocument.editors LIKE ' => '%' . $this->Session->read('User.id') . '%'
						),
						$accessConditions
					)));					
				if($result)$menus["$value"][$documentType] = $result;
			}
		}
		$this->set('menus',$menus);
	}

	public function clean_table_names($tableName = null){
		$tableName = $this->_clean_table_names($tableName);
		return $tableName;
	}

	public function _clean_table_names($tableName = null){
		if($tableName){
			$tableName = ltrim(rtrim($tableName));
			$tableName = preg_replace('/[\x00-\x1F\x7F]/u', '', $tableName);
			$tableName = preg_replace('/[[:^print:]]/', '', $tableName);
			$tableName = str_replace('/', '_', $tableName);
			$tableName = str_replace('-', '_', $tableName);
			$tableName = str_replace('&', '_', $tableName);
			$tableName = ltrim(rtrim(strtolower($tableName)));
			$tableName = preg_replace('/[\@\.\;\()~!@#$%^&*_+" "-]+/', '_', $tableName);
			$tableName = preg_replace('/ */', '', $tableName);
			$tableName = preg_replace('/\\s+/', '_', $tableName); 
			$tableName = preg_replace('/-*-/', '_', $tableName);
			$tableName = preg_replace('/_*_/', '_', $tableName);
			$tableName = substr($tableName, 0, 25);
			$tableName = preg_replace('/^([^a-zA-Z0-9])*/', '', $tableName);
			$tableName = rtrim($tableName,"_");			
			return $tableName;
		}
	}

	public function save_doc() {
		$this->autoRender = false;
		$local = $this->request->params['named'];		
		if (($body_stream = file_get_contents("php://input")) === FALSE) {
			echo "Bad Request";
		}
		$data = json_decode($body_stream, TRUE);
		if ($data["status"] == 2) {
			$data = json_decode($body_stream, TRUE);			
			if($local['tmp']){
				$record_id = $local['record_id'] = 'tmp'. DS . $local['record_id'];
			}else{
				$record_id = $local['record_id'] = $local['record_id'];
			}			
			$path_for_save = WWW_ROOT . 'files' . DS . $local['company_id'] . DS . $local['controller'] . DS . $local['record_id'];
			if($local['controller'] == 'qc_documents'){
				if($this->request->params['named']['tmp']){					
					$file_type = $local['filetype'];
					$file_name = 'blank';
					$fileName = $file_name . '.' . $file_type;					
					$file_for_save = $path_for_save . DS . $fileName;
					$testfolder = new Folder($path_for_save);
					$testfolder->create($path_for_save);
					chmod($path_for_save, 0777);
					chmod($file_for_save, 0777);
					$downloadUri = $data["url"];					
					if (file_get_contents($downloadUri) === FALSE) {

					} else {
						$new_data = file_get_contents($downloadUri);
						if (file_put_contents($file_for_save, $new_data,LOCK_EX)) {
							$qcdoc = $this->QcDocument->find('first', array('recursive' => - 1, 'conditions' => array('QcDocument.id' => $this->request->params['named']['record_id'])));
							$key = $this->_generate_onlyoffice_key($record_id . date('Ymdhis'));
							$updates = json_decode($qcdoc['QcDocument']['version_keys'],true);
							if(is_array($updates)){
								$last_modified = date('Y-m-d H:i:s');
								$updates[] = array('version_key'=>$key,'modified'=>$last_modified,'by'=>$local['user']);
							}else{
								$last_modified = date('Y-m-d H:i:s');
								$updates[] = array('version_key'=>$key,'modified'=>$last_modified,'by'=>$local['user']);
							}
							$qcdoc['QcDocument']['version_keys'] = json_encode($data['history']);
							$qcdoc['QcDocument']['file_key'] = $key;
							$qcdoc['QcDocument']['data_received'] = 'added from save_doc';
							$qcdoc['QcDocument']['file_status'] = 1;
							$qcdoc['QcDocument']['last_saved'] = $last_modified;
							$qcdoc['QcDocument']['version'] = 1;							
							$this->QcDocument->create();
							$this->QcDocument->save($qcdoc['QcDocument']);
							$json = [
								"created" => date("Y-m-d H:i:s"),
								'uid'=>$data['history']['changes'][0]['user']['id'],
								'name'=>$data['history']['changes'][0]['user']['name'],
							];							
							// write the encoded file information to the createdInfo.json file
							$version = 1;
							$history_file_for_save = $path_for_save . DS .$fileName.'-hist' . DS . $version;
							$historyFolder = new Folder($history_file_for_save);
							$historyFolder->create($history_file_for_save);
							chmod($history_file_for_save, 0777);
							file_put_contents($history_file_for_save . DS . "createdInfo.json", json_encode($json, JSON_PRETTY_PRINT));
						} else {
						}
					}
				}else{
					$this->loadModel('QcDocument');
					$qcdoc = $this->QcDocument->find('first', array('recursive' => - 1, 'conditions' => array('QcDocument.id' => $this->request->params['named']['record_id'])));
					$file_type = $qcdoc['QcDocument']['file_type'];
					$file_name = $qcdoc['QcDocument']['title'];
					$document_number = $qcdoc['QcDocument']['document_number'];
					$document_version = $qcdoc['QcDocument']['revision_number'];					
					// save previous file version
					$fileName = $document_number . '-' . $file_name . '-' . $document_version;
					$fileName = $this->_clean_table_names($fileName);
					$fileName = $fileName . '.' . $file_type;
					$file_for_save = $path_for_save . DS . $fileName;
					$testfolder = new Folder($path_for_save);
					$testfolder->create($path_for_save);
					$version = $qcdoc['QcDocument']['version'];
					$history_file_for_save = $path_for_save . DS .$fileName.'-hist' . DS . $version;
					$historyFolder = new Folder($history_file_for_save);
					$historyFolder->create($history_file_for_save);
					chmod($path_for_save, 0777);
					chmod($file_for_save, 0777);
					chmod($history_file_for_save, 0777);
					$downloadUri = $data["url"];
					// update_version if(version to be updated of not);
					// if($qcdoc['QcDocument']['update_version'] == 1){
					$preFileName = 'prev' . '.'.$file_type;
					$url = $preFile_for_save = $history_file_for_save . DS . $preFileName;
					$url = str_replace('\/','/',$url);
					// adding diff.zip file
					if(isset($data["changesurl"])){
						try{
							$changesData = file_get_contents($data["changesurl"]);
							file_put_contents($history_file_for_save. DS . "diff.zip", $changesData, LOCK_EX);
						}catch(Exception $e){

						}
					}
					
					// adding prev.ext file
					$fromFile = new File($file_for_save);
					$fromFile->copy($preFile_for_save,true);
					$newHistory = array(						
						'key'=>$data['key'],
						'version'=>$version,
						'changes'=> $data['history']['changes'],
						'serverVersion'=>$data['history']['serverVersion'],
						'created'=>$data['history']['changes'][0]['created'],
						'user'=>array(
							'id'=>$data['history']['changes'][0]['user']['id'],
							'name'=>$data['history']['changes'][0]['user']['name'],
							'url'=>$url
						),
					);
					file_put_contents($history_file_for_save . DS . "changes.json", json_encode($newHistory), LOCK_EX);
					// adding key.txt file
					file_put_contents($history_file_for_save . DS . "key.txt", $data['key'], LOCK_EX);
					$downloadUri = $data["url"];
					$historyData = $data["changesurl"];
					$versions = json_decode($qcdoc['QcDocument']['versions'],true);
					$versions[] = $newHistory;
					if (file_get_contents($downloadUri) === FALSE) {
							
					} else {
						$new_data = file_get_contents($downloadUri);
						if (file_put_contents($file_for_save, $new_data,LOCK_EX)) {
							$key = $this->_generate_onlyoffice_key($record_id . date('Ymdhis'));
							if(is_array($updates)){
								$last_modified = date('Y-m-d H:i:s');
							}else{
								$last_modified = date('Y-m-d H:i:s');
							}
							$qcdoc['QcDocument']['version_keys'] = json_encode($data['history']);
							$qcdoc['QcDocument']['file_key'] = $key;
							$qcdoc['QcDocument']['file_status'] = 1;
							$qcdoc['QcDocument']['last_saved'] = $last_modified;
							$qcdoc['QcDocument']['version'] = $version + 1;
							$qcdoc['QcDocument']['versions'] = json_encode($versions);
							$this->QcDocument->create();
							if($this->QcDocument->save($qcdoc['QcDocument'],false)){
								
							}else{
								
							}
							// update process document
							$this->loadModel('Process');
							$process = $this->Process->find('first',array('recursive'=>-1, 'conditions'=>array('Process.qc_document_id'=>$record_id)));							
							if($process){
								$this->requestAction(array('controller'=>'processes','action'=>'update_process',$process['Process']['id']));
							}
							// also update custom table document.
							$this->loadModel('CustomTable');
							$customTables = $this->CustomTable->find('list',array('conditions'=>array('CustomTable.qc_document_id'=>$qcdoc['QcDocument']['id'])));							
							if($customTables){
								foreach($customTables as $custom_table_id => $name){
									$qcpfile = WWW_ROOT . 'files' . DS . $local['company_id'] . DS . 'qc_documents' . DS . $qcdoc['QcDocument']['id'] . DS . $fileName;
									$tofile = WWW_ROOT . 'files' . DS . $local['company_id'] . DS . 'custom_tables' . DS . $custom_table_id . DS . $fileName;
									if (copy($qcpfile, $tofile)) {
									} else {
										
									}
								}
							}
						} else {							
						}						
					}
				}
			}else if($local['controller'] == 'processes'){
				$this->loadModel('Process');
				$process = $this->Process->find('first', array('recursive' => - 1, 'conditions' => array('Process.id' => $this->request->params['named']['record_id'])));
				$file_type = $process['Process']['file_type'];
				$file_name = $process['Process']['file_name'];				
				$fileName = $file_name;				
				$file_for_save = $path_for_save . DS . $fileName;
				$testfolder = new Folder($path_for_save);
				$testfolder->create($path_for_save);
				chmod($path_for_save, 0777);
				chmod($file_for_save, 0777);
				$downloadUri = $data["url"];
				if (file_get_contents($downloadUri) === FALSE) {					
				} else {
					$new_data = file_get_contents($downloadUri);
					if (file_put_contents($file_for_save, $new_data)) {
						$key = $this->_generate_onlyoffice_key($record_id . date('Ymdhis'));
						$process['Process']['file_key'] = $key;
						$process['Process']['file_status'] = 1;
						$process['Process']['last_saved'] = date('Y-m-d H:i:s');
						$process['Process']['version_keys'] = $process['Process']['version_keys'] . ', ' . $key;
						$this->Process->create();
						$this->Process->save($process['Process']);
					} else {						
					}					
				} 
			}			
		}
		echo "{\"error\":0}";
	}

	public function save_file() {
		$this->autoRender = false;
		$local = $this->request->params['named'];		
		if (($body_stream = file_get_contents("php://input")) === FALSE) {
			echo "Bad Request";
		}
		$data = json_decode($body_stream, TRUE);		
		if ($data["status"] == 2) {
			$data = json_decode($body_stream, TRUE);
			$record_id = $this->request->params['named']['record_id'];
			$path_for_save = WWW_ROOT . 'files' . DS . $local['company_id'] . DS . 'files' . DS . $local['record_id'];			
			$this->loadModel('File');			
			$qcdoc = $this->File->find('first', array('recursive' => - 1, 'conditions' => array('File.id' => $this->request->params['named']['record_id'])));			
			$file_type = $qcdoc['QcDocument']['file_type'];
			$file_name = $qcdoc['QcDocument']['title'];
			$file_name = $this->_clean_table_names($file_name);
			$fileName = $file_name . '.' . $file_type;
			$file_for_save = $path_for_save . DS . $fileName;
			$testfolder = new Folder($path_for_save);
			$testfolder->create($path_for_save);
			chmod($path_for_save, 0777);
			chmod($file_for_save, 0777);
			$downloadUri = $data["url"];
			if (file_get_contents($downloadUri) === FALSE) {
			} else {
				$new_data = file_get_contents($downloadUri);
				if (file_put_contents($file_for_save, $new_data)) {
					$key = $this->_generate_onlyoffice_key($record_id . date('Ymdhis')); 
					$updates = json_decode($qcdoc['QcDocument']['version_keys'],true);
					if(is_array($updates)){
						$last_modified = date('Y-m-d H:i:s');
						$updates[] = array('version_key'=>$key,'modified'=>$last_modified,'by'=>$local['user']);
					}else{
						$last_modified = date('Y-m-d H:i:s');
						$updates[] = array('version_key'=>$key,'modified'=>$last_modified,'by'=>$local['user']);
					}
					$qcdoc['QcDocument']['version_keys'] = json_encode($data["history"]);
					$qcdoc['File']['file_key'] = $key;
					$qcdoc['File']['file_status'] = 1;
					$qcdoc['File']['last_saved'] = $last_modified;
					$this->File->create();
					$this->File->save($qcdoc['file']);
				} else {
				}
			}
		}
		echo "{\"error\":0}";
	}

	public function curl($type = null, $api_controller = null, $path = null,$data = null,$linkedTosWithDisplay = null){
		if(empty($type))$type = $this->request->params['named']['type'];
		$linkedTosWithDisplay = json_decode($this->request->params['named']['linkedTosWithDisplay']);
		if(empty($api_controller))$api_controller = $this->request->params['named']['api_controller'];		
		if($type == 'post'){ 
			if(empty($data))$data = $this->request->params['named']['data'];
			$controllers = array();
			$aCtrlClasses = App::objects('controller');
			$skip = array('AppController', 'ApprovalsController', 'ApprovalCommentsController', 'CustomTablesController', 'FilesController', 'RecordsController', 'UserSessionsController');
			foreach ($aCtrlClasses as $controller) {
				if (!in_array($controller, $skip)) {
					$controller = str_replace('Controller', '', $controller);
					$name = $this->CustomTable->find('first', array('recursive' => 1, 'conditions' => array('CustomTable.table_name LIKE' => "%" . Inflector::underscore($controller)), 'fields' => array('CustomTable.id', 'CustomTable.name', 'CustomTable.table_version')));
					if ($name) {
						$controller = Inflector::classify($controller);
						$linkedTos[$controller] = $name['CustomTable']['name'] . " ver " . $name['CustomTable']['table_version'];
					} else {
						$linkedTos[$controller] = $controller;
					}
				}
			}			
			if(!empty($this->request->params['named']['path']))$path = $this->request->params['named']['path'];			
			$str = 'company_id:'.$this->Session->read('User.company_id');
			$path = Configure::read('ApiPath').$api_controller."/".$path."/".$this->Session->read('User.company_id')."/api:false/". $str;
			$response = $this->_curl_post($path,$linkedTos,$data,$linkedTosWithDisplay);
			return $response;
		}
		if($type == 'get'){
			$str = 'company_id:'.$this->Session->read('User.company_id');
			if(!empty($this->request->params['named']['type']))$str1 = '/type:'.$this->request->params['named']['type'];
			$path = Configure::read('ApiPath').$api_controller."/".$path."/".$this->Session->read('User.company_id')."/api:true/". $str.$str1;
			$response = $this->_curl_get($path);
			return $response;
		}
	}

	public function _curl_post($path = null, $linkedTos = null, $data = null,$linkedTosWithDisplay = null){ 
		$response = array();
		$postdata = array(
			'linkedTos'=>$linkedTos,
			'data'=>$data,
			'linkedTosWithDisplay'=>$linkedTosWithDisplay
		);
		$curl = curl_init();		
		curl_setopt_array($curl, array(
			CURLOPT_URL => $path,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_SSL_VERIFYHOST => 0,
          	CURLOPT_SSL_VERIFYPEER => 0,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => "------WebKitFormBoundary7MA4YWxkTrZu0gW\r\nContent-Disposition: form-data; name=\"data\"\r\n\r\n".json_encode($postdata)."\r\n------WebKitFormBoundary7MA4YWxkTrZu0gW--",
			CURLOPT_HTTPHEADER => array(
				"authorization: Basic YWJjOjEyMw==",
				"cache-control: no-cache",
				"content-type: multipart/form-data; boundary=----WebKitFormBoundary7MA4YWxkTrZu0gW",
				"postman-token:".base64_encode($this->Session->read('User.company_id')),			
			),
		));
		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		if($err){
			echo "Error";
		}
		if($response){
			return $response;
		}
	}

	public function _curl_get($path = null){
		$response = array();
		$curl = curl_init();
		$auth = base64_encode('user:'.$this->Session->read('User.company_id'));
		$head ='Authorization: Basic '. $auth;
		curl_setopt_array($curl, array(
			CURLOPT_URL => $path,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_SSL_VERIFYHOST => 0,
          	CURLOPT_SSL_VERIFYPEER => 0,
			CURLOPT_CUSTOMREQUEST => "GET",
			CURLOPT_HTTPHEADER => array( 
			),
		));
		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		if($err){
		}
		if($response){
			return $response;
		}
	}

	public function _file_clean_up(){
		$this->loadModel('File');
		$files = $this->File->find('all',array('conditions'=>array('File.record_id'=>'tmp'), 'fields'=>array('File.id','File.name','File.file_type'),'recursive'=>-1));
		foreach($files as $file){
			$check_files = Configure::read('files') . DS . 'files' . DS . $file['File']['id'] . DS . $file['File']['name'] .'.'.$file['File']['file_type'];
			if(!file_exists($check_files)){
				$this->File->delete($file['File']['id']);
			}
		}
        // remove empty folders
		$file_folder = New Folder(Configure::read('files') . DS . 'files');
		$folders = $file_folder->read();
		foreach($folders[0] as $check_folder){
			$folder_to_check = New Folder(Configure::read('files') . DS . 'files' . DS . $check_folder);
			$check_files = $folder_to_check->read();
			if(count($check_files[1]) == 0){
                //delete folder and also dete file table record                
				$this->File->delete($check_folder);
				$folder_to_check->delete();
			}else{
				$file = $this->File->find('count',array('conditions'=>array('File.id'=>$check_folder)));
				if($file == 0){
					$folder_to_check->delete();
				}
			}
		}        
	}

	public function _prepare_update(){
		$this->_file_clean_up();

		$this->cacheAction = false;
		$modelName = $this->modelClass;
		$this->$modelName->CustomTable->virtualFields = array(
			'document_schedule'=>'select schedule_id from qc_documents where qc_documents.id LIKE CustomTable.qc_document_id  LIMIT 1',
			'process_schedule'=>'select schedule_id from processes where processes.id LIKE CustomTable.process_id  LIMIT 1'
		);
		$customTable = $this->$modelName->CustomTable->find('first',array('conditions'=>array('CustomTable.id'=>$this->request->params['named']['custom_table_id']),'recursive'=>-1));	
		$lastval = $this->fetch_last_record($modelName, $this->$modelName->displayField, 'DESC',null);
		$this->set('lastval',$lastval);
		

		if($customTable['CustomTable']['document_schedule'])$schedule_id = $customTable['CustomTable']['document_schedule'];
		if($customTable['CustomTable']['process_schedule'])$schedule_id = $customTable['CustomTable']['process_schedule'];
		$this->loadModel('Schedule');
		$schedule = $this->Schedule->find('first',array('conditions'=>array('Schedule.id'=>$schedule_id),'recursive'=>-1,'fields'=>array('Schedule.id','Schedule.name')));
		if($customTable){
			if($customTable['CustomTable']['publish'] != 1){
				$this->Session->setFlash(__('This table is not yet available for adding data'));
				$this->redirect(array('action' => 'index','custom_table_id'=>$this->request->params['named']['custom_table_id'],'qc_document_id'=>$this->request->params['named']['qc_document_id'],'process_id'=>$this->request->params['process_id'])); 
			}
			$this->set('customTable',$customTable);
		}else{
			$this->Session->setFlash(__('Unknown table'));
			$this->redirect(array('action' => 'index','custom_table_id'=>$this->request->params['named']['custom_table_id'],'qc_document_id'=>$this->request->params['named']['qc_document_id'],'process_id'=>$this->request->params['process_id']));
		}		
		$modelName = $this->modelClass;
		if(!empty($this->request->params['named']['qc_document_id'])){ // treat as qc			
			$this->loadModel('QcDocument');
			$qc_document = $this->QcDocument->find('first',array('conditions'=>array('QcDocument.id'=>$this->request->params['named']['qc_document_id'])));
			$file_type = $qc_document['QcDocument']['file_type'];
			$file_name = $file_name_without_ext = $this->_clean_table_names($qc_document['QcDocument']['title']);
			$document_number = $qc_document['QcDocument']['document_number'];
			$document_version = $qc_document['QcDocument']['revision_number'];
			$file_name = $document_number.'-'.$file_name.'-'.$document_version;
			$file_name = $this->_clean_table_names($file_name);
			$file_name = $file_name .'.'.$file_type;
			$file_name_without_ext = $document_number.'-'.$file_name_without_ext.'-'.$document_version;
			$file_name_without_ext = $this->_clean_table_names($file_name_without_ext);
		}

		if(!empty($this->request->params['named']['process_id'])){ // treat as qc 
			$this->loadModel('Process');
			$process = $this->Process->find('first',array('conditions'=>array('Process.id'=>$this->request->params['named']['process_id'])));
			$file_type = $process['Process']['file_type'];
			$file_name_without_ext = $process['Process']['name'];
			$file_name = $process['Process']['name'].'.'.$file_type;
		}
		switch ($schedule['Schedule']['name']) {
			case 'Daily':
			$previous_record_date = date('Y-m-d');
			break;
			case 'Weekly':
			$previous_record_date = date('Y-m-d',strtotime('-1 week'));
			break;
			case 'Monthly':
			$previous_record_date = date('Y-m-d',strtotime('-1 month'));
			break;
			case 'Quarterly':
			$previous_record_date = date('Y-m-d',strtotime('-15 days'));
			break;
			case 'Yearly':
			$previous_record_date = date('Y-m-d',strtotime('-1 year'));
			break;
			case 'Half-Yearly':
			$previous_record_date = date('Y-m-d',strtotime('-6 months'));
			break;
			case 'None':
			$previous_record_date = null;
			break;
		}
		if(!empty($customTable['CustomTable']['qc_document_id']) && !isset($this->request->params['named']['qc_document_id'])){
			$this->Session->setFlash(__('Select form first'));
			$this->redirect(array('controller'=>'custom_tables', 'action' => 'index'));
		}else{
			$document = $this->_qc_document_header($this->request->params['named']['qc_document_id']);
		}
		if(!empty($customTable['CustomTable']['process_id']) && !isset($this->request->params['named']['process_id'])){ 
			$this->Session->setFlash(__('Select form first'));
			$this->redirect(array('controller'=>'custom_tables', 'action' => 'index'));
		}else{
			$process = $this->_process_header($this->request->params['named']['process_id']);
		}
		if(!empty($this->request->params['named']['custom_table_id'])){ // treat as custom forms
			try{
				// check if its a child qc document
				$this->$modelName->CustomTable->virtualFields = array(
					'parent_qc_id'=>'select count(*) from `qc_documents` where `qc_documents`.`id` LIKE CustomTable.qc_document_id'
				);
				$customTable = $this->$modelName->CustomTable->find('first',array('conditions'=>array('CustomTable.id'=>$this->request->params['named']['custom_table_id']),'recursive'=>-1));
			}catch(Exception $e){
			}
			// check if file exists
			// if record schedule is daily and user is adding new record on a same day/ or schedule is monthly and user is adding record again in a same month, load existing file for update
			// find lass added file if schedule is not null
			$this->loadModel('File');		
			if($customTable['CustomTable']['parent_qc_id'] == 0){
				if($previous_record_date != null){
					// if data_update_type == 2, remove created by field
					if($qc_document['QcDocument']['data_update_type'] ==2 ){
						$existing_file = $this->File->find('first',array('conditions'=>array(
							'File.model'=>$this->modelClass,
							'File.controller' => $this->request->controller,
							'DATE(File.created)'=> date('Y-m-d',strtotime($previous_record_date))
						)));
					}else{
						$existing_file = $this->File->find('first',array('conditions'=>array(
							'File.created_by'=>$this->Session->read('User.id'),
							'File.model'=>$this->modelClass,
							'File.controller' => $this->request->controller,
							'DATE(File.created)'=> date('Y-m-d',strtotime($previous_record_date))
						)));
					}					
				}else{					
					$existing_file = null;
				}
			}else{				
				if(isset($this->request->params['named']['parent_record_id']) && ($this->request->params['named']['parent_record_id'] != -1 || $this->request->params['named']['parent_record_id'] != null) ){
					$findRec = $this->$modelName->find('first',array('recursive'=>-1,'conditions'=>array($modelName.'.parent_id' => $this->request->params['named']['parent_record_id'])));
					if($findRec[$modelName]['file_id']){
						$existing_file = $this->File->find('first',array('conditions'=>array('File.id'=>$findRec[$modelName]['file_id'])));
					}
				}
			}
			if($existing_file){
				return $existing_file;
			}else{
				// delete tmp files
				$files_to_delete = $this->File->find('all',array('conditions'=>array(
					'File.record_id'=>'tmp',
					'File.user_id'=>$this->Session->read('User.id'),
					'File.custom_table_id'=>$customTable['CustomTable']['id']),
				'recursive'=>-1,
				));

				foreach($files_to_delete as $file_to_delete){
					unlink(Configure::read('files') . DS . 'files' . $file_to_delete['File']['id'] . 'DS '. $file_to_delete['File']['name'].'.'.$file_to_delete['File']['file_type']);
					$this->File->delete($file_to_delete['File']['id']);
				}
				if(!empty($this->request->params['named']['qc_document_id'])){
					$fromFile = Configure::read('files') . DS . 'custom_tables' . DS . $customTable['CustomTable']['id'] . DS . $file_name;
				}
				if(!empty($this->request->params['named']['process_id'])){
					$fromFile = Configure::read('files') . DS . 'processes' . DS . $this->request->params['named']['process_id'] . DS . $file_name;
				}				
				// copy file only if data_type is document or both. Skip copying file of data_type = 1
				if($document['QcDocument']['data_type'] != 1) { // data type is document or both then open for editing.
					if(file_exists($fromFile)){
						$data['File']['name'] = $file_name_without_ext;
						$data['File']['file_type'] = $file_type;
						$data['File']['file_key'] = $this->_generate_onlyoffice_key($this->request->params['named']['custom_table_id'].date('Ymdhis'));
						$data['File']['qc_document_id'] = $qc_document['QcDocument']['id'];
						$data['File']['process_id'] = $process['Process']['id'];
						$data['File']['custom_table_id'] = $this->request->params['named']['custom_table_id'];
						$data['File']['model'] = $this->modelClass;
						$data['File']['controller'] = $this->request->controller;
						$data['File']['prepared_by'] = $data['File']['modified_by'] = $this->Session->read('User.employee_id');
						$data['File']['created'] = date('Y-m-d h:i:s');
						$data['File']['created_by'] = $data['File']['user_id'] = $this->Session->read('User.id');
						$data['File']['record_id'] = 'tmp';
						$data['File']['file_status'] = 0;
						$data['File']['data_received'] = 'added from prepare_update';
						$this->File->create();
						if($this->File->save($data, false)){
							$toFile = Configure::read('files') . DS . 'files' . DS . $this->File->id . DS . $file_name;

							if(file_exists($toFile))unlink($toFile);
							
							$folder = new Folder(); 
							$dir = new Folder(Configure::read('files') . DS . 'files' . DS . $this->File->id, true, 0777);

							if (copy($fromFile, $toFile)) {
								$data['File']['id'] = $this->File->id;

								// also delete last unused files
								// same controller - model - user - last_saved == null 
								// record = tmp
								$filesToDelete = $this->File->find('all',array('conditions'=>array(
									'File.controller'=>$this->request->controller,
									'File.model'=>$this->modelClass,
									'File.record_id'=>'tmp',
									'File.user_id'=>$this->Session->read('User.id'),
									'File.last_saved'=>NULL,
									'File.id !=' => $this->File->id
								),'recursive'=>-1));
								
								foreach($filesToDelete as $fileToDelete){
									$this->File->delete(array($fileToDelete['File']['id']));
									// delete dir
									$dirToDelete = new Folder(Configure::read('files') . DS . 'files' . DS . $fileToDelete['File']['id']);
									$dirToDelete->delete();

								}

								return $data;
							}else{
								
								$this->File->delete(array($this->File->id));
								$this->Session->setFlash(__('Failed to copy file'));
								$this->redirect(array('action' => 'index','table_type'=>$this->request->params['named']['table_type']));$this->redirect(array('action' => 'index'));
							}
						}
					}else{
						// $this->Session->setFlash(__('Unable to copy file.'));
						// $this->redirect(array('action' => 'index','table_type'=>$this->request->params['named']['table_type']));
						}
				}else{
					// open same file in view mode
					$data['File']['id'] = $customTable['CustomTable']['id'];
					$data['File']['name'] = $file_name_without_ext;
					$data['File']['file_type'] = $file_type;
					$data['File']['file_key'] = $this->_generate_onlyoffice_key($this->request->params['named']['custom_table_id'].date('Ymdhis'));
					$data['File']['qc_document_id'] = $qc_document['QcDocument']['id'];
					$data['File']['process_id'] = $process['Process']['id'];
					$data['File']['custom_table_id'] = $this->request->params['named']['custom_table_id'];
					$data['File']['model'] = $this->modelClass;
					$data['File']['controller'] = $this->request->controller;
					$data['File']['prepared_by'] = $data['File']['modified_by'] = $this->Session->read('User.employee_id');
					$data['File']['created'] = date('Y-m-d h:i:s');
					$data['File']['created_by'] = $data['File']['user_id'] = $this->Session->read('User.id');
					$data['File']['record_id'] = 'tmp';
					$data['File']['file_status'] = 0;
					$data['File']['data_received'] = 'added from prepare_update';					
					return $data;
				}
			}
		}
		if(!empty($customTable['CustomTable']['qc_document_id']) && !isset($this->request->params['named']['qc_document_id'])){
			$this->Session->setFlash(__('Select form first'));
			$this->redirect(array('controller'=>'custom_tables', 'action' => 'index'));
		}else{
			$document = $this->_qc_document_header($this->request->params['named']['qc_document_id']);
		}

		if(!empty($customTable['CustomTable']['process_id']) && !isset($this->request->params['named']['process_id'])){ 
			$this->Session->setFlash(__('Select form first'));
			$this->redirect(array('controller'=>'custom_tables', 'action' => 'index'));
		}else{
			$process = $this->_process_header($this->request->params['named']['process_id']);
		} 
	}

	public function _json(){
		$linkedTables = $this->CustomTable->find('all',array(
			'recursive'=>0,
			'fields'=>array(
				'CustomTable.id',
				'CustomTable.name',
				'CustomTable.table_name',
				'CustomTable.field_name',
				'CustomTable.field_value',
				'QcDocument.id',
				'QcDocument.parent_document_id',
				'QcDocument.title',
			),
			'conditions'=>array(
				'CustomTable.table_type !=' => 2,
				'QcDocument.parent_document_id' => $this->request->params['named']['qc_document_id'],
			)));
		$t = 0;
		foreach($linkedTables as $linkedTable){
			if($this->viewVars[Inflector::variable($this->modelClass)][$this->modelClass][$linkedTable['CustomTable']['field_name']] == $linkedTable['CustomTable']['field_value']){
				$load[$t]['custom_table_id'] = $linkedTable['CustomTable']['id'];
				$load[$t]['qc_document_id'] = $linkedTable['QcDocument']['id'];
				$load[$t]['name'] = $linkedTable['QcDocument']['title'];
				$load[$t]['table_name'] = $linkedTable['CustomTable']['table_name'];
				$load[$t]['action'] = 'add';
			}else{
				$load[$t]['custom_table_id'] = $linkedTable['CustomTable']['id'];
				$load[$t]['qc_document_id'] = $linkedTable['QcDocument']['id'];
				$load[$t]['name'] = $linkedTable['QcDocument']['title'];
				$load[$t]['table_name'] = $linkedTable['CustomTable']['table_name'];
				$load[$t]['action'] = 'index';
			} 
			$t++;
		}		
		$this->set('loadLinkedTables',$load);
	}
	public function _view(){		
		if($this->Session->read('User.is_mr') == false && $this->Session->read('User.is_view_all') == false){
			if(
				$this->viewVars[Inflector::variable($this->modelClass)][$this->modelClass]['prepared_by'] != $this->Session->read('User.employee_id') || 
				$this->viewVars[Inflector::variable($this->modelClass)][$this->modelClass]['modified_by'] != $this->Session->read('User.employee_id') || 
				$this->viewVars[Inflector::variable($this->modelClass)][$this->modelClass]['approved_by'] != $this->Session->read('User.employee_id') || 
				$this->viewVars[Inflector::variable($this->modelClass)][$this->modelClass]['created_by'] != $this->Session->read('User.id')
			){
				if($this->request->controller != 'qc_documents' && isset($this->request->params['named']['custom_table_id'])){

					if(isset($this->request->params['named']['custom_table_id'])){
						$this->_customtableacces();
					}else{
						$this->Session->setFlash(__('You are not authorized to view this section :00'), 'default', array('class' => 'alert alert-danger'));
						$this->redirect(array('controller' => 'users', 'action' => 'access_denied',$this->action));
					}					
				}
			}
		}else if($this->Session->read('User.is_mr') == false && $this->Session->read('User.is_view_all') == true){
			if(
				$this->viewVars[Inflector::variable($this->modelClass)][$this->modelClass]['branchid'] != $this->Session->read('User.branch_id') && 
				!in_array($this->viewVars[Inflector::variable($this->modelClass)][$this->modelClass]['branchid'], json_decode($this->Session->read('User.assigned_branches'),true))
			){
				// allow user to edit profile
				if(
					($this->request->controller == 'employees' && $this->request->params['pass'][0] == $this->Session->read('User.employee_id')) || 
					$this->request->controller == 'qc_documents'
				){

				}else{
					$this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
					$this->redirect(array('controller' => 'users', 'action' => 'access_denied',$this->action,'01'));
				}				
			}
		}
		// check if this document has linked documents with table
		$this->loadModel('CustomTable');
		$linkedTables = $this->CustomTable->find('all',array(
			'recursive'=>0,
			'fields'=>array(
				'CustomTable.id',
				'CustomTable.name',
				'CustomTable.table_name',
				'CustomTable.field_name',
				'CustomTable.field_value',
				'QcDocument.id',
				'QcDocument.parent_document_id',
				'QcDocument.title',
			),
			'conditions'=>array(
				'CustomTable.table_type !=' => 2,
				'QcDocument.parent_document_id' => $this->request->params['named']['qc_document_id'],
			)));
		$t = 0;
		foreach($linkedTables as $linkedTable){
			if($this->viewVars[Inflector::variable($this->modelClass)][$this->modelClass][$linkedTable['CustomTable']['field_name']] == $linkedTable['CustomTable']['field_value']){
				$load[$t]['custom_table_id'] = $linkedTable['CustomTable']['id'];
				$load[$t]['qc_document_id'] = $linkedTable['QcDocument']['id'];
				$load[$t]['name'] = $linkedTable['QcDocument']['title'];
				$load[$t]['table_name'] = $linkedTable['CustomTable']['table_name'];
				$load[$t]['action'] = 'add';
			}else{
				$load[$t]['custom_table_id'] = $linkedTable['CustomTable']['id'];
				$load[$t]['qc_document_id'] = $linkedTable['QcDocument']['id'];
				$load[$t]['name'] = $linkedTable['QcDocument']['title'];
				$load[$t]['table_name'] = $linkedTable['CustomTable']['table_name'];
				$load[$t]['action'] = 'index';
			} 
			$t++;
		}		
		$this->set('loadLinkedTables',$load);
	}

	public function _update_belongTos($data = null){
		try{
			foreach($data['belongsTos'] as $bModel => $belongsToData){
				$modelName = $this->modelClass;
				foreach($this->$modelName->belongsTo as $mname => $details){
					if($details['className'] == $bModel){
						//Field from main form
						$fieldFromMainForm = $data[$modelName][$details['foreignKey']];
						if($details['foreignKey'] != 'qc_document_id'){
							$this->loadModel($bModel);
							$bModelRecord = $this->$bModel->find('first',array('recursive'=>-1,'conditions'=>array($bModel.'.id'=>$fieldFromMainForm)));
							if(is_array($belongsToData) && is_array($bModelRecord[$bModel])){
								$dataarray[$bModel] = array_merge($bModelRecord[$bModel],$belongsToData);
								$this->$bModel->create();
								$this->$bModel->save($dataarray[$bModel],false);
							}							
						}
					}
				}
			}
		}catch(Exception $e){

		}
	}

	public function _add(){
		$modelName = $this->modelClass;			
		foreach($this->request->data[$modelName] as $key => $value){
			if(is_array($value)){
				if($this->request->data[$modelName][$key]['name']){
					$this->request->data[$modelName][$key] = json_encode($value); 
					$this->request->data['Files'][] = $value;
				}else{
					$this->request->data[$modelName][$key] = json_encode($value);
				}
			}
		}
		
		if($this->request->data[$modelName]['additional_files'])$additionalFiles = json_decode($this->request->data[$modelName]['additional_files'],true);
		if($this->request->params['named']['parent_record_id']){
			$this->request->data[$modelName]['parent_id'] = $this->request->params['named']['parent_record_id'];
		}
		// $this->request->data['Approval'][$modelName]['publish'] = $this->request->data['Approval'][$modelName]['publish'];		
		if($this->action == 'add'){
			$this->request->data[$modelName]['prepared_by'] = $this->Session->read('User.employee_id');
			$this->request->data[$modelName]['approval_step_id'] = $this->request->data['Approval'][$modelName]['approval_step_id'];
		}
		if($this->action == 'edit'){
			// get exisiting record 
			$existingRecord = $this->$modelName->find('first',array('conditions'=>array($modelName.'.id'=>$this->request->data[$modelName]['id']),'recursive'=>-1));
			$this->request->data[$modelName]['prepared_by'] = $existingRecord[$modelName]['prepared_by'];
		}
		
		if ($this->$modelName->save($this->request->data,false)) {
			$new_record_id = $this->$modelName->id;			
			$this->_update_belongTos($this->request->data);			
			$this->loadModel('File');
			$file = $this->File->find('first',array('conditions'=>array('File.id'=>$this->request->data[$modelName]['file_id'])));
			if($file){
				$file['File']['record_id'] = $new_record_id;
				$file['File']['file_status'] = 0;
				$file['File']['data_received'] = 'added from _add';
				$file['File']['last_modified'] = date('Y-m-d H:i:s');
				$this->File->create();
				try{
					$this->File->save($file); 
				}catch (Exception $e){

				}
			}
			// add/update additional files
			if($additionalFiles){
				if(count($additionalFiles)){
					foreach($additionalFiles as $additionalFile){
						if($additionalFile != '' && strlen($additionalFiles[0]) == 36){
							$file = $this->File->find('first',array('conditions'=>array('File.id'=>$additionalFile)));
							if($file && $file['File']['record_id'] == 'tmp'){
								$file['File']['record_id'] = $new_record_id;
								$file['File']['file_status'] = 0;
								$file['File']['data_received'] = 'added from _add';
								$file['File']['last_modified'] = date('Y-m-d H:i:s');
								$this->File->create();
								try{
									$this->File->save($file); 
								}catch (Exception $e){

								}
							}
						}
					}
				}	
			}
			if(isset ($this->request->data['Files']))$this->_upload_custom_files($this->request->data['Files'],$this->$modelName->id,$this->request->params['named']['custom_table_id']);
			
			// get hasMany
			$hasManies = $this->$modelName->hasMany;
			foreach($hasManies as $model => $fields){
				
				if($this->request->data[$model]){
					$this->loadModel($model);
					unset($this->request->data[$model]['count']);
					unset($this->request->data[$model]['file_id']);
					unset($this->request->data[$model]['file_key']);
					foreach($this->request->data[$model] as $cdata_old){
						$cdata = array();
						foreach($cdata_old as $key => $value){
							if(is_array($value)){
								$cdata[$key] = json_encode($value);
								if(!empty($value['tmp_name'])){
									$file = $value;
								}
							}else{
								$cdata[$key] = $value;
							}
						}						
						$this->$model->create();
						$cdata['parent_id'] = $this->$modelName->id;
						$cdata['created'] = date('Y-m-d H:i:s');
						$cdata['modified'] = date('Y-m-d H:i:s');
						$cdata['prepared_by'] = $this->Session->read('User.employee_id');
						$cdata['created_by'] = $this->Session->read('User.id');
						$cdata['branchid'] = $this->Session->read('User.branch_id');
						$cdata['departmentid'] = $this->Session->read('User.department_id');
						$cdata['qc_document_id'] = $this->request->data[$modelName]['qc_document_id'];		
						try{
							$this->$model->save($cdata,false);
							$path = WWW_ROOT . 'files' . DS . $this->Session->read('User.company_id') . DS . 'record_files' . DS . $cdata['custom_table_id'] . DS . $this->$model->id ;
							$recordfilesfolder = new Folder($path);
							$recordfilesfolder->create($path,0777);
							$move = move_uploaded_file($file['tmp_name'], $path . DS . $file['name']);
						}catch(Exception $e) {
					 		$this->Session->setFlash(__('Child record not added/ Updated.'), 'default', array('class' => 'alert alert-danger'));
						}
					}
				}
			}
			
			if ($this->_show_approvals()) $this->_save_approvals($this->$modelName->id);
			$this->Session->setFlash(__('Record has been saved'));
			try{
				// trigger email here
				$this->_trigger_email($existingRecord); 
			}catch(Exception $e){
				$this->Session->setFlash(__('Email Trigger failed.')); 
			}
		 	// if parent record id is available, redirect to parent record view page
			$this->loadModel('QcDocument');
			$this->loadModel('CustomTable');
			if(isset($this->request->params['named']['parent_record_id']) && $this->request->params['named']['parent_record_id']){
				$this_document = $this->QcDocument->find('first',array(
					'fields'=>array(
						'QcDocument.id',
						'QcDocument.title',
						'QcDocument.parent_document_id',
					),
					'recursive'=>-1, 
					'conditions'=>array('QcDocument.id'=>$this->request->params['named']['qc_document_id']))); 
				if($this_document){
					$parent_document = $this->QcDocument->find('first',array(
					'conditions'=>array('QcDocument.id'=>$this_document['QcDocument']['parent_document_id'])));
				}
				if($parent_document){
					foreach($parent_document['CustomTable'] as $cTable){
						$this->CustomTable->virtualFields = array(
							'rec'=>'select count(*) from `'.$cTable['table_name'].'` where id LIKE "'.$this->request->params['named']['parent_record_id'].'" ');
						$parent_rec = $this->CustomTable->find('first',array(						
							'conditions'=>array('CustomTable.qc_document_id'=>$parent_document['QcDocument']['id'],'CustomTable.rec >'=>0),'recursive'=>-1));
						if($parent_rec){
							$this->redirect(array(
								'controller'=>$parent_rec['CustomTable']['table_name'], 
								'action' => 'view',
								$this->request->params['named']['parent_record_id'],
								'custom_table_id'=>$parent_rec['CustomTable']['custom_table_id'],
								'qc_document_id'=>$parent_document['QcDocument']['id'],
								'process_id'=>$this->request->params['named']['process_id']));
						}
					}
				}
			}
			$this->redirect(array('action' => 'index','custom_table_id'=>$this->request->params['named']['custom_table_id'],'qc_document_id'=>$this->request->params['named']['qc_document_id'],'process_id'=>$this->request->params['named']['process_id']));
		} else {
			$this->Session->setFlash(__('Record could not be saved. Please, try again.'));
		}
	}

	public function _upload_custom_files($files = null, $id = null,$custom_table_id = null){
		if($custom_table_id == null && $custom_table_id != -1){
			$this->Session->setFlash(__('Unable to save file.'));
			return false;
		}else{
			$path = WWW_ROOT . 'files' . DS . $this->Session->read('User.company_id') . DS . 'record_files' . DS . $custom_table_id . DS . $id ;
			$recordfilesfolder = new Folder($path_for_save);
			$recordfilesfolder->create($path);
			chmod($path, 0777);
			foreach($files as $file){
				$result = move_uploaded_file($file['tmp_name'], $path . DS . $file['name']); 
				if($result == false){
					$this->Session->setFlash(__('Unable to upload file.'));
				}
			}
			return true;
		}
		
	}

	public function reloaddocument_copy(){
		$additionalFiles = json_decode(base64_decode($this->request->params['pass'][3]),true);
		if($additionalFiles){
			if(count($additionalFiles)){
				$this->loadModel('File');
				foreach($additionalFiles as $additionalFile){
					if($additionalFile != '' && strlen($additionalFiles[0]) == 36){
						$file = $this->File->find('first',array( 'recursive'=>-1, 'conditions'=>array('File.id'=> $additionalFile)));
						if($file){
							$this->set('fileEdit',$file);
							$this->set('is_qc',false);
							$this->render('/Elements/load_extra_document');
						}
					}
				}
			}else{
			}
		}else{
		}		
	}

	public function reloadfile($file_id = null){
		if($file_id){
			$this->loadModel('File');
			$file = $this->File->find('first',array('conditions'=>array('File.record_id'=>$file_id)));
			$this->set('fileEdit',$file);
			$this->set('is_qc',false);
		}
		$this->render('/Elements/load_extra_document');
	}

	public function reloadrecordfile($file_id = null){
		if($file_id){
			$this->loadModel('File');
			$file = $this->File->find('first',array('conditions'=>array('File.id'=>$file_id)));
			$this->set('fileEdit',$file);
			$this->set('is_qc',false);
			$this->set('controller',$file['File']['controller']);
		}
		$this->render('/Elements/load_extra_document');
	}

	public function _trigger_email($existingRecord = null){
		$modelName = $this->modelClass;
		unset($existingRecord[$modelName]['sr_no']);
		unset($existingRecord[$modelName]['created']);
		unset($existingRecord[$modelName]['modified']);
		unset($existingRecord[$modelName]['company_id']);
		unset($existingRecord[$modelName]['branchid']);
		unset($existingRecord[$modelName]['departmentid']);
		unset($existingRecord[$modelName]['status_user_id']);
		unset($existingRecord[$modelName]['record_status']);
		unset($existingRecord[$modelName]['file_key']);
		unset($existingRecord[$modelName]['file_id']);
		$this->loadModel('CustomTrigger');
		if($this->action == 'add')$action = 0;
		if($this->action == 'edit')$action = 1;
		// check for field changes
		// this idealy only should execute for edit
		if($this->action == 'edit'){
			$triggers = $this->CustomTrigger->find('all',array('recursive'=>-1, 'conditions'=>array(
				'CustomTrigger.custom_table_id'=>$this->request->params['named']['custom_table_id'],				
			)));
			
			if($triggers){
				// check for each field change and if there is a change, trigger email as per conditions
				foreach($triggers as $trigger){
					
					
			
					if(
						$this->request->data[$modelName][$trigger['CustomTrigger']['field_name']] != $existingRecord[$modelName][$trigger['CustomTrigger']['field_name']] && 
						$trigger['CustomTrigger']['changed_field_value'] == $this->request->data[$modelName][$trigger['CustomTrigger']['field_name']]
					){
						$tos = $this->_get_tos($trigger);
						if($tos){
							$subject = $trigger['CustomTrigger']['name'];
							$message = $trigger['CustomTrigger']['message'];
							$this->_send_trigger_email($tos,$subject,$message);

							$subject = $message = '';
							$trigger = array();
							// action based trigger 
							$trigger = $this->CustomTrigger->find('first',array('recursive'=>-1, 'conditions'=>array(
								'CustomTrigger.custom_table_id'=>$this->request->params['named']['custom_table_id'],
								'CustomTrigger.action'=>$action,
							)));
							if($trigger){
								$tos = $this->_get_tos($trigger);
								if($tos){
									$subject = $trigger['CustomTrigger']['name'];
									$message = $trigger['CustomTrigger']['message'];
									$this->_send_trigger_email($tos,$subject,$message);
								}
							}

						}
					}else{
						if($trigger['CustomTrigger']['notify_departments'] == true){
							if($existingRecord[$modelName]['custom_table_id']){
								$customTable = $this->$modelName->CustomTable->find('first',array(
									'conditions'=>array('CustomTable.id'=>$existingRecord[$modelName]['custom_table_id']),
									'recursive'=>-1,
									'fields'=>array('CustomTable.id','CustomTable.fields')));

								if($customTable){
									$fields = json_decode($customTable['CustomTable']['fields'],true);
									foreach($fields as $field){
										if($field['linked_to'] == 'Departments' && $field['display_type'] == 4){
											$departments = json_decode($existingRecord[$modelName][$field['field_name']],true);											
										}elseif($field['linked_to'] == 'Departments' && $field['display_type'] == 3){
											$departments[] = $existingRecord[$modelName][$field['field_name']];
										}
										$this->_get_emails_by_bdd(0,$departments);
										

										if($field['linked_to'] == 'Branches' && $field['display_type'] == 4){
											$branches = json_decode($existingRecord[$modelName][$field['field_name']],true);											
										}elseif($field['linked_to'] == 'Departments' && $field['display_type'] == 3){
											$branches[] = $existingRecord[$modelName][$field['field_name']];
										}
										$this->_get_emails_by_bdd(1,$branches);

										if($field['linked_to'] == 'Designations' && $field['display_type'] == 4){
											$desiganations = json_decode($existingRecord[$modelName][$field['field_name']],true);											
										}elseif($field['linked_to'] == 'Departments' && $field['display_type'] == 3){
											$desiganations[] = $existingRecord[$modelName][$field['field_name']];
										}

										$this->_get_emails_by_bdd(2,$desiganations);
									}

									$tos = $this->_get_tos($trigger);
									if($tos){
										$subject = $trigger['CustomTrigger']['name'];
										$message = $trigger['CustomTrigger']['message'];
										$this->_send_trigger_email(array_keys($tos),$subject,$message);
									}
								}
							}							
						}
					}
				}
			}
		}		
	} 

	public function _get_emails_by_bdd($type = null, $values = null){	
		$this->loadModel('Employee');
		if($type == 0){
			$employees = $this->Employee->find('all',array('recursive'=>-1,'fields'=>array('Employee.id','Employee.name','Employee.office_email'),
				'conditions'=>array('Employee.department_id'=>$values)));			
		}

		if($type == 1){
			$employees = $this->Employee->find('all',array('recursive'=>-1,'fields'=>array('Employee.id','Employee.name','Employee.office_email'),
				'conditions'=>array('Employee.branch_id'=>$values)));			
		}

		if($type == 2){
			$employees = $this->Employee->find('all',array('recursive'=>-1,'fields'=>array('Employee.id','Employee.name','Employee.office_email'),
				'conditions'=>array('Employee.desiganation_id'=>$values)));			
		}
		
		foreach($employees as $employee){
				if($employee)$tos[$employee['Employee']['office_email']] = $employee['Employee']['office_email'];	
		}

		return $tos;
	}

	public function _get_tos($trigger = null){
		$this->loadModel('Employee');
		$modelName = $this->modelClass;
		foreach($this->$modelName->belongsTo as $belongs){
			if($belongs['className'] == 'Department')$department_field = $belongs['foreignKey'];
		}
		if($trigger['CustomTrigger']['notify_user'] != -1){		
			$employee = $this->Employee->find('first',array('recursive'=>-1,'fields'=>array('Employee.id','Employee.office_email'),
				'conditions'=>array('Employee.id'=>$this->request->data[$modelName][$trigger['CustomTrigger']['notify_user']])));
			if($employee)$tos[$employee['Employee']['office_email']] = $employee['Employee']['office_email'];
		}
		if($trigger['CustomTrigger']['notify_admins'] == true){
			$this->Employee->User->virtualFields = array(
				'office_email'=>'select `employees`.`office_email` from `employees` where `employees`.`id` LIKE User.employee_id  LIMIT 1'
			);
			$users = $this->Employee->User->find('all',array(
				'conditions'=>array('User.is_mr'=>1),
				'fields'=>array('User.id','User.employee_id','User.office_email'),
				'recursive'=>-1,
			));
			if($users){
				foreach($users as $user){
					$tos[$user['User']['office_email']] = $user['User']['office_email'];
				}
			}
		}
		$employee = array();
		if($trigger['CustomTrigger']['notify_users']){
			$notify_users = json_decode($trigger['CustomTrigger']['notify_users'],true); 
			if($notify_users){
				$employees = $this->Employee->find('list',array('fields'=>array('Employee.id','Employee.office_email'), 'conditions'=>array('Employee.is_hod'=>1, 'Employee.id'=>$notify_users)));
				foreach($employees as $employee){
					$tos[$employee['Employee']['office_email']] = $employee['Employee']['office_email'];
				}
			}
		}
		$employee = array();
		if($trigger['CustomTrigger']['notify_hods'] == true && $department_field){
			// get HoDs for these departments 
			// get added daprtments
			$departments = json_decode($this->request->data[$modelName][$department_field],true);
			if($departments){
				foreach($departments as $department){
					$hod = $this->Employee->find('first',array('fields'=>array('Employee.id','Employee.office_email'), 'conditions'=>array('Employee.is_hod'=>1, 'Employee.department_id'=>$department)));
					if($hod){
						$tos[$hod['Employee']['office_email']] = $hod['Employee']['office_email'];
					}
				}
			}
		}
		if($trigger['CustomTrigger']['hod_departments']){
			// get HoDs for these departments 
			$notify_hods = $this->Employee->find('list',array('fields'=>array('Employee.id','Employee.office_email'), 'conditions'=>array('Employee.is_hod'=>1, 'Employee.department_id'=>json_decode($trigger['CustomTrigger']['hod_departments'],true))));
			foreach($notify_hods as $employee){
				$tos[$employee['Employee']['office_email']] = $employee['Employee']['office_email'];
			}
		}
		return $tos;
	}

	public function _send_trigger_email($tos = null,$subject = null, $message = null){		
		try{
			App::uses('CakeEmail', 'Network/Email');
			$EmailConfig = new CakeEmail("fast");
			$EmailConfig->to($tos);
			$EmailConfig->subject($subject);
			$EmailConfig->template('emailTrigger');
			$EmailConfig->viewVars(array(
				'record' => $this->request->data[$this->modelClass]['name'].''.$this->request->data[$this->modelClass]['title'],
				'employee' => $this->Session->read('User.name'),
				'date_time' => date('Y-m-d h:i:s'),
				'h2tag'=>' Email from Email Triggers',
				'msg_content'=>$message));
			$EmailConfig->emailFormat('html');
			$EmailConfig->send();
		} catch(Exception $e) {
			CakeLog::write('debug',json_encode($e));
		}
	}

	public function _fetch_file($id = null){		
		$document = $this->_qc_document_header($this->request->params['named']['qc_document_id']);			
		if($this->action == 'edit'){
			if(!empty($customTable['CustomTable']['qc_document_id']) && !isset($this->request->params['named']['qc_document_id'])){
				$this->Session->setFlash(__('Select form first'));
				$this->redirect(array('controller'=>'custom_tables', 'action' => 'index'));
			}else{
				$document = $this->_qc_document_header($this->request->params['named']['qc_document_id']);
			}
			if(!empty($customTable['CustomTable']['process_id']) && !isset($this->request->params['named']['process_id'])){
				$this->Session->setFlash(__('Select form first'));
				$this->redirect(array('controller'=>'custom_tables', 'action' => 'index'));
			}else{
				$process = $this->_process_header($this->request->params['named']['process_id']);
			}
		}
		$customTable = $this->request->params['named']['custom_table_id'];
		$modelName = $this->modelClass;
		$customTable = $this->$modelName->CustomTable->find('first',array('conditions'=>array('CustomTable.id'=>$this->request->params['named']['custom_table_id']),'recursive'=>-1));
		$this->set('customTable',$customTable);
		// 0 = document
		// 1 = data
		// 2 = both		
		if($document['QcDocument']['data_type'] && $document['QcDocument']['data_type'] == 1){
			// load table file
			$file_type = $document['QcDocument']['file_type'];
			$file_name = $file_name_without_ext = $this->_clean_table_names($document['QcDocument']['title']);
			$document_number = $document['QcDocument']['document_number'];
			$document_version = $document['QcDocument']['revision_number'];
			$file_name = $document_number.'-'.$file_name.'-'.$document_version;
			$file_name = $this->_clean_table_names($file_name);
			$file_name = $file_name .'.'.$file_type;
			$file_name_without_ext = $document_number.'-'.$file_name_without_ext.'-'.$document_version;
			$file_name_without_ext = $this->_clean_table_names($file_name_without_ext);
			$file['File']['id'] = $customTable['CustomTable']['id'];
			$file['File']['name'] = $file_name_without_ext;
			$file['File']['file_type'] = $file_type;
			$file['File']['file_key'] = $this->_generate_onlyoffice_key($this->request->params['named']['custom_table_id'].date('Ymdhis'));
			$file['File']['qc_document_id'] = $document['QcDocument']['id'];
			$file['File']['process_id'] = $process['Process']['id'];
			$file['File']['custom_table_id'] = $this->request->params['named']['custom_table_id'];
			$file['File']['model'] = $this->modelClass;
			$file['File']['controller'] = $this->request->controller;
			$file['File']['prepared_by'] = $file['File']['modified_by'] = $this->Session->read('User.employee_id');
			$file['File']['created'] = date('Y-m-d h:i:s');
			$file['File']['created_by'] = $file['File']['user_id'] = $this->Session->read('User.id');
			if($id)$file['File']['record_id'] = $id;
			else $file['File']['record_id'] = 'tmp';
			$file['File']['file_status'] = 0;
			$file['File']['data_received'] = 'added from prepare_update';	
			return $file;
		}else{
			try{
				if($document['QcDocument']['data_update_type'] == 2){
					if(isset($this->viewVars[Inflector::variable($this->modelClass)][$this->modelClass]) && !empty($this->viewVars[Inflector::variable($this->modelClass)][$this->modelClass]['file_id'])){
						$this->loadModel('File');
						$file = $this->File->find('first',array('recursive'=>-1, 'conditions'=>array(
							'File.id'=>$this->viewVars[Inflector::variable($this->modelClass)][$this->modelClass]['file_id'],							
							'File.controller'=>$this->request->controller
						)));
						if($file){
							$file['File']['pre_file_key'] = $file['File']['file_key'];							
							$file['File']['file_status'] = 0;
							$file['File']['data_received'] = 'added from fetch_file: mostly edit';
							$this->File->create();
							$this->File->save($file,false);
							return $file;
						}else{
							return false;
						}
					}elseif(isset($this->request->data[$this->modelClass]['file_id']) &&  !empty($this->request->data[$this->modelClass]['file_id'])){
							$this->loadModel('File');
							$file = $this->File->find('first',array('recursive'=>-1, 'conditions'=>array(
								'File.id'=>$this->request->data[$this->modelClass]['file_id'],							
								'File.controller'=>$this->request->controller
							)));
							if($file){
								$file['File']['pre_file_key'] = $file['File']['file_key'];								
								$file['File']['file_status'] = 0;
								$file['File']['data_received'] = 'added from fetch_file: mostly edit';
								$this->File->create();
								$this->File->save($file,false);
								return $file; 
							}
						}
					exit;
				}else{
					$this->loadModel('File');
					$file = $this->File->find('first',array('recursive'=>-1, 'conditions'=>array('File.record_id'=>$id,'File.controller'=>$this->request->controller)));					
					if($file){
						$file['File']['pre_file_key'] = $file['File']['file_key'];
						$file['File']['file_key'] = $this->_generate_onlyoffice_key($file['File']['id'].date('Ymdhis'));
						$file['File']['file_status'] = 0;
						$file['File']['data_received'] = 'added from fetch_file: mostly edit';
						$this->File->create();
						$this->File->save($file,false);
						return $file; 
					}else{
						return false;
					}
				}					
			}catch(Exception $e){
				return false;
			}
		}		
	}

	public function _clear_cake_cache(){
		Cache::clear();
		clearCache();
		exec('rm '. APP . 'tmp' . DS . 'cache' . DS . 'persistent/*');
		exec('rm '. APP . 'tmp' . DS . 'cache' . DS . 'models/*');
		exec('rm '. APP . 'tmp' . DS . 'cache' . DS . 'views/*');
	}

	public function add_date_new($date = null){
		$this->autoRender = false;
		$default_date_type = array('days','weeks','months','years');
		$fromDate = date('Y-m-d',strtotime($this->request->data['fromDate']));
		$newdate = date('Y-m-d',strtotime('+'.$this->request->data['linkedTos']['default_date_number'].' '.$default_date_type[$this->request->data['linkedTos']['default_date_type']].' ',strtotime($fromDate)));
		return $newdate;
	}

	public function _check_lock($id = null){
		if($this->action == 'edit' || $this->action == 'view'){
			$this->loadModel('RecordLock');
			$rec = $this->RecordLock->find('first',array( 
				'conditions'=>array('RecordLock.lock_table_id'=>$this->modelClass)));			
			if($rec){
				$table = Inflector::classify($rec['Table']['table_name']); 
				foreach(json_decode($rec['Table']['fields'],true) as $field){
					if($field['linked_to'] != -1 && Inflector::classify($field['linked_to']) == $this->modelClass){
						$chk_field = $field['field_name'];
					}else{
						$chk_field = 'id';
					}
				}
				try{$this->loadModel($table);}catch (Exception $e){}
				try{
					$condition = array($table.'.'.$rec['RecordLock']['table_field'] => $rec['RecordLock']['csvoption'],
						$table.'.'.$chk_field => $this->request->params['pass'][0]);
					$record = $this->$table->find('count',array(
						'conditions'=>$condition));
				}catch (Exception $e){} 				
				if($record > 0){
					if($this->action == 'edit'){ 
						$this->redirect(array('action' => 'view',$this->request->params['pass'][0]));
					}else{
						$this->set('lock_message',$rec['RecordLock']['message']);
					}
				}
			}
		}
	}

	public function load_document_version(){
		$this->set('thisKey',$this->request->data['key']);
		$this->render('/Elements/load_document_version');
	}

	public function generate_pdf(){
		if ($this->request->is('post') || $this->request->is('put')) {

		}else{
			$this->render('/Elements/sign_pdf');
		}
	}

	public function onlyofficechk(){
		$this->autoRender = false;
		$this->loadModel('UserSession');
		$session = $this->UserSession->find('first',array('recursive'=>-1,'fields'=>array('UserSession.id','UserSession.user_id','UserSession.start_time','UserSession.end_time'), 'conditions' => array(
			'UserSession.user_id'=>$this->data['user_id'],
			'UserSession.id'=>$this->data['user_session_id'])));
		if($session){
			return json_encode($session);
		}else{
			return json_encode($session);
		}
		exit;
	}

	public function check_document(){
		$this->layout = 'ajax';
		$is_qc = false;
		$field = str_replace('data['.$this->modelClass.'][','', $this->request->params['pass'][1]);
		$field = str_replace(']','',$field);
		if($this->request->params['named']['custom_table_id']){
			$this->loadModel('CustomTable');
			$customTable = $this->CustomTable->find('first',array('conditions'=>array('CustomTable.id'=>$this->request->params['named']['custom_table_id']),'recursive'=>-1,'fields'=>array('CustomTable.id','CustomTable.fields')));
			$fields = json_decode($customTable['CustomTable']['fields'],true);
			foreach($fields as $fs){
				if($fs['field_name'] == $field){
					$showdocs = $fs['showdocs'];
					$showdocs_mode = $fs['showdocs_mode'];
					$showdocs_copy = $fs['showdocs_copy'];
					$this->set('fs',$fs);
					// try and get the record details 
					$recordModel = Inflector::Classify($fs['linked_to']);
					$this->loadModel($recordModel);					
					$thisRecord  = $this->$recordModel->find('first',array('conditions'=>array($recordModel.'.id'=>$this->request->params['pass'][0]),'recursive'=>-1));					
					if($thisRecord){
						$this->set('thisRecord',$thisRecord);
						$this->set('thisModel',$recordModel);
					}
				}
			}
		}
		$modelName = $this->modelClass;
		// check for additional files
		$record = $this->$modelName->find('first',array(
			'conditions'=>array($modelName.'.id'=>$this->request->params['named']['record_id']),
			'recursive'=>-1,
			'fields'=>array($modelName.'.id',$modelName.'.additional_files')));
		$additionalFiles = json_decode($record[$modelName]['additional_files'],true);
		$belongs = $this->$modelName->belongsTo;
		$this->loadModel('File');
		$ref = trim(str_replace(Router::url('/', true) .$this->request->controller,'-', $this->referer()));
		$ref = explode('/',$ref);
		if($ref[1] == 'add')$action = 'add';
		if($ref[1] == 'edit')$action = 'edit';		
		foreach($belongs as $table => $data ){
			if($data['foreignKey'] == $field){
				$newModel = $data['className'];
				$this->loadModel($newModel);				
				if($newModel != 'QcDocument'){	
					if($this->request->params['named']['showdocs'] == 1){
						$fileloaded = false;
						if(
							($this->request->params['named']['showdocs_mode'] == 0) || 
							($this->request->params['named']['showdocs_mode'] == 0 && $this->request->params['named']['showdocs_copy'] == 1)){
							//just load original file in view mode
							$file = $this->File->find('first',array('recursive'=>-1, 'conditions'=>array('File.model'=>$newModel,'File.record_id'=>$this->request->params['pass'][0])));
							$this->set('is_qc',false);
							$this->set('fileEdit',$file);
							$fileloaded = true;
						}
						if($this->request->params['named']['showdocs_mode'] == 1 && $this->request->params['named']['showdocs_copy'] == 0){
							// load original file in edit mode
							$file = $this->File->find('first',array('recursive'=>-1, 'conditions'=>array('File.model'=>$newModel,'File.record_id'=>$this->request->params['pass'][0])));
							$this->set('is_qc',false);
							$this->set('fileEdit',$file);
							$fileloaded = true;
						}
						if($fileloaded == false){
							if($action == 'add'){
								if($this->request->params['named']['showdocs_mode'] == 1 && $this->request->params['named']['showdocs_copy'] == 1){
									//just load original file in view mode
									$file = $this->File->find('first',array('recursive'=>-1, 'conditions'=>array('File.model'=>$newModel,'File.record_id'=>$this->request->params['pass'][0])));
									if($file){
										$newFile = $file;
										unset($newFile['File']['id']);
										unset($newFile['File']['sr_no']);
										$newFile['File']['model'] = $modelName;
										$newFile['File']['controller'] = $this->request->controller;
										$newFile['File']['created_by'] = $newFile['File']['modified_by'] = $this->Session->read('User.id');
										$newFile['File']['prepared_by'] = $newFile['File']['approved_by'] = $this->Session->read('User.employee_id');
										$newFile['File']['file_key'] = $this->_generate_onlyoffice_key($file['File']['id'].date('Ymdhis'));
										$newFile['File']['version_keys'] = NULL;
										$newFile['File']['data_received'] = 'additional files';
										// delete any existing files by this user which are tmp+additional files
										$filesToDelete = $this->File->find('all',array('conditions'=>array(
											'File.data_received'=>'additional files',
											'File.created_by'=>$this->Session->read('User.id')
										),'recursive'=>-1));
										$path = Configure::read('files') . DS . 'files';
										foreach($filesToDelete as $fileToDelete){
											$toDelete = new Folder($path . DS . $fileToDelete['File']['id']);
											$toDelete->delete();									
											$this->File->delete($fileToDelete['File']['id']);
										}
										// now add new file										
										$this->File->create();
										if($this->File->save($newFile,false)){
											$newFile['File']['id'] = $this->File->id;
											$doc  = $path . DS  . $file['File']['id'] . DS . $file['File']['name'].'.'.$file['File']['file_type'];											
											$docsave = $path . DS . $newFile['File']['id'] . DS . $file['File']['name'].'.'.$file['File']['file_type'];
											if(!file_exists($docsave)){
												$newFolder = new Folder();
												$newFolder->create(WWW_ROOT . DS . 'files' . DS . $this->Session->read('User.company_id') . DS . 'files' . DS . $this->File->id,0777);
												copy($doc,$docsave);
											}
										}								
										$this->set('is_qc',false);
										$this->set('fileEdit',$newFile);
									}
								}
							}else{
								foreach($additionalFiles as $additionalFile){
									$file = $this->File->find('first',array('conditions'=>array('File.id'=>$additionalFile),'recursive'=>-1));
									$this->set('is_qc',false);
									$this->set('fileEdit',$file);
								}
							}
						}
					}
					// for qc documents
				}else{
					$is_qc = true;
					$this->loadModel('QcDocument');
					$qcfile = $this->QcDocument->find('first',array('recursive'=>-1,
						'conditions'=>array(
							'QcDocument.id'=>$this->request->params['pass'][0],
							'OR'=>array(
								'QcDocument.prepared_by'=> $this->Session->read('User.employee_id'),
								'QcDocument.approved_by'=> $this->Session->read('User.employee_id'),
								'QcDocument.issued_by'=> $this->Session->read('User.employee_id'),
								'QcDocument.branches LIKE' => '%'.$this->Session->read('User.branch_id').'%',
								'QcDocument.departments LIKE' => '%'.$this->Session->read('User.department_id').'%',							
								'QcDocument.user_id LIKE' => '%'.$this->Session->read('User.id').'%',
							)
						)
					));
					// check if file already exists
					if($qcfile && $showdocs_copy != 0){
						if($showdocs_copy == 1){
							$this->loadModel('File');
							$file = $this->File->find('first',array('conditions'=>array(
								'File.record_id'=>$this->request->params['named']['record_id'],
								'File.qc_document_id'=>$qcfile['QcDocument']['id']
							),'recursive'=>-1,'order'=>array('File.sr_no'=>'ASC')));							
							// add new file to files table
							// copy qc file to new files id folder			
							if($file){		
								$file['File']['modified_by'] = $this->Session->read('User.employee_id');
								$file['File']['modified'] = date('Y-m-d h:i:s');
								$file['File']['file_status'] = 0;
								$file['File']['data_received'] = 'Already found '. $this->request->params['named']['record_id'];									
								$this->File->create();
								if($this->File->save($file,false)){
								}
							}else{
								if(empty($this->request->params['named']['record_id']))$this->request->params['named']['record_id'] = 'tmp';
								$file_type = $qcfile['QcDocument']['file_type'];
								$file_name = $file_name_without_ext = $this->_clean_table_names($qcfile['QcDocument']['title']);
								$document_number = $qcfile['QcDocument']['document_number'];
								$document_version = $qcfile['QcDocument']['revision_number'];
								$file_name = $document_number.'-'.$file_name.'-'.$document_version;
								$file_name = $this->_clean_table_names($file_name);
								$file_name_without_ext = $document_number.'-'.$file_name_without_ext.'-'.$document_version;
								$file_name_without_ext = $this->_clean_table_names($file_name_without_ext);
								$file['File']['name'] = $file_name;
								$file['File']['file_type'] = $qcfile['QcDocument']['file_type'];
								$file['File']['file_key'] = $this->_generate_onlyoffice_key($qcfile['QcDocument']['file_type'].date('Ymdhis'));
								$file['File']['qc_document_id'] = $qcfile['QcDocument']['id'];
								$file['File']['process_id'] = $process['Process']['id'];
								$file['File']['custom_table_id'] = $this->request->params['named']['custom_table_id'];
								$file['File']['model'] = 'QcDocument';
								$file['File']['controller'] = 'qc_documents';
								$file['File']['prepared_by'] = $file['File']['modified_by'] = $this->Session->read('User.employee_id');
								$file['File']['created'] = date('Y-m-d h:i:s');
								$file['File']['created_by'] = $file['File']['user_id'] = $this->Session->read('User.id');
								$file['File']['record_id'] = $this->request->params['named']['record_id'];
								$file['File']['file_status'] = 0;
								$file['File']['data_received'] = 'added from copy file 2';
								$this->File->create();
								if($this->File->save($file,false)){					
									$doc  = WWW_ROOT . DS . 'files' . DS . $this->Session->read('User.company_id') . DS . 'qc_documents' . DS . $qcfile['QcDocument']['id'] . DS . $file['File']['name'].'.'.$file['File']['file_type'];
									$docsave = WWW_ROOT . DS . 'files' . DS . $this->Session->read('User.company_id') . DS . 'files' . DS . $this->File->id . DS . $file['File']['name'].'.'.$file['File']['file_type'];
									if(!file_exists($docsave)){
										$newFolder = new Folder();
										$newFolder->create(WWW_ROOT . DS . 'files' . DS . $this->Session->read('User.company_id') . DS . 'files' . DS . $this->File->id,0777);
										copy($doc,$docsave);	
									}
								}
								$file['File']['id'] = $this->File->id;
							}
							$this->set('is_qc',false);
							$this->set('fileEdit',$file);
						}else{
							if($qcfile){
								$filepath = WWW_ROOT . DS . 'files' . DS . $this->Session->read('User.company_id') . DS . 'qc_documents' . DS . $file['File']['id'];
								$filename = $filepath . DS . $file['File']['name'].'.'.$file['File']['file_type'];								
								if(file_exists($filename)){
									$this->set('is_qc',$is_qc);
									$this->set('fileEdit',$qcfile);
								}else{
									// return false;
								}
							}
						}
					}
				}
			}
		}
		$this->view = '/Elements/load_extra_document';
		return $this->viewVars;
	}

	public function delete_document(){
		if($this->request->controller != 'qc_documents'){
			$this->view = '/Elements/delete_document';
			if ($this->request->is('post')) {
				$this->loadModel('User');
				$user = $this->User->find('first', array('conditions' => array('User.status' => 1, 'User.soft_delete' => 0, 'User.publish' => 1, 'User.username' => $this->Session->read('User.username'))));
				if ($user) {
					if (trim($user['User']['password']) != trim(Security::hash($this->request->data[Inflector::classify($this->request->controller)]['password'], 'md5', true))) {
						$this->Session->setFlash(__('Incorrect password', true), 'default', array('class' => 'alert-danger'));
					} else {
						$getid = explode('/', (string)base64_decode((string)$this->request->data[Inflector::classify($this->request->controller)]['url']));
                        $id = count($getid) > 1 ? (string)$getid[count($getid) - 2] : '';
                        $name = (string)end($getid);
                        // M9 correction 14 (staging, 28 Oct 2026): the attachment must exist and belong to a record of this screen;
                        // it is deleted by its stored id, and the file name may not leave its folder.
                        $this->loadModel('File');
                        $m9f = $this->File->find('first', array('recursive' => -1, 'conditions' => array('File.id' => $id)));
                        $m9why = $this->_m9_file_delete_check($m9f, $name);
                        if ($m9why !== '') { $this->_m9_ac_refuse($m9why); return; }
                        $this->File->delete($m9f['File']['id'], false);
                        $m9p = Configure::read("files") . DS . 'files' . DS . $m9f['File']['id'] . DS . $name;
                        if (is_file($m9p)) unlink($m9p);
						$this->Session->setFlash(__('File Deleted', true), 'default', array('class' => 'alert-success'));
						$this->redirect($this->request->data[Inflector::classify($this->request->controller)]['ref']);
					}
				}
			} else {
				$this->set('ref', $this->referer());
			}
		}
	}

	public function _returnDetaultField($allFields = null){
		foreach($allFields as $field){
			if($field['linked_to'] != -1){
				$model = Inflector::classify($field['linked_to']);
				try{
					$this->loadModel($model);
					$linkedTosWithDisplay[Inflector::classify($field['field_name'])] = $this->$model->displayField;
				}catch (Exception $e){
				}
			}
		}
		return $linkedTosWithDisplay;
	}

	public function checkunique($value = null,$field_name = null){
		$this->autoRender = false;
		$model = $this->modelClass;
		$rec = $this->$model->find('count',array('conditions'=>array($model.'.'.$field_name => base64_decode($value))));
		if($rec == 0){
			return false;
		}else{
			return true;
		}

	}

	public function code_input_main($id = null){
		$table = $this->CustomTable->find('first',array('fields'=>array('CustomTable.id','CustomTable.add_form_script','CustomTable.edit_form_script','CustomTable.table_name'),'recursive'=>-1, 'conditions'=>array('CustomTable.id'=>$id)));
		if($table){
			$this->set('table',$table);
		}
		$this->view = '/Elements/code_input_main';

	}

	public function return_value_for_dropdown($model = null, $field = null,$id = null){
		$this->autoRender = false;
		try{
			$model = Inflector::classify($model);
			$this->loadModel($model);
			$rec = $this->$model->find('first',array('conditions'=>array($model.'.id' => $id),'fields'=>array($model.'.id',$model.'.'.$field)));
			if($rec){
				return $rec[$model][$field];
			}else{
				return false;
			}	
		}catch (Exception $e){
			return false;
		}
		exit;
	}

	public function return_options_for_dropdown($parent = null, $child = null,$id = null){
		try{
			$parent = Inflector::Classify($parent);
			$child = Inflector::Classify($child);		
			$this->autoRender = false;
			$this->loadModel($child);
			$belongs = $this->$child->belongsTo;			
			if(in_array($parent,array_keys($belongs))){
				$key = $belongs[$parent]['foreignKey'];
			}		
			if($key){
				$rec = $this->$child->find('list',array('conditions'=>array($child.'.'.$key => $id)));	
				if($rec){
					$con_str.= '<option value=-1>Select</option>';
					foreach ($rec as $key => $value) {
						$con_str.= '<option value=' . $key . '>' . $value . '</option>';
					}
					return $con_str;
				}else{
					return false;
				}	
			}else{
				return false;
			}
		}catch (Exception $e){
			return false;
		}
		exit;
	}

	public function fetch_last_record($model = null, $field = null, $order = null, $id = null){
		try{
			$this->loadModel($model);
			$rec = $this->$model->find('first',array('fields'=>array($model.'.'.$field,$model.'.created') ,'order'=>array($model.'.created' => $order),'recursive'=>-1));
			if($rec){				
				return $rec[$model][$field];
			}else{					
				return 0;
			}
		}catch (Exception $e){
			return false;
		}		
	}

	public function _fetch_record($model = null, $key = null, $field = null, $order = null, $id = null){
		$parent = Inflector::Classify($model);
		try{
			$this->loadModel($model);
			if($order == 'first')$order = ' DESC';
			if($order == 'last')$order = ' ASC';
			if(empty($field))$field = $this->$model->displayField;

			$rec = $this->$model->find('first',array('conditions'=>array($model.'.'.$key => $id),'recursive'=>-1));			
			if($rec){
				return $rec[$model][$field];
			}else{
				return false;
			}
		}catch (Exception $e){
			return false;
		}
		exit;
	}
	public function fetch_record($model = null, $key = null, $field = null, $order = null, $id = null){
		
		$this->autoRender = false;
		$parent = Inflector::Classify($model);
		try{
			$this->loadModel($model);
			if($order == 'first')$order = ' DESC';
			if($order == 'last')$order = ' ASC';
			if(empty($field))$field = $this->$model->displayField;

			$rec = $this->$model->find('first',array('conditions'=>array($model.'.'.$key => $id),'recursive'=>-1));			
			if($rec){
				return $rec[$model][$field];
			}else{
				return false;
			}
		}catch (Exception $e){
			return false;
		}
		exit;
	}

	public function field_fetch($model = null, $fieldTobeChanged = null, $label = false, $readonly = null){
		$selectedModelName = $model;
		$currModel = $this->modelClass;
		try{
			$rec = $this->$currModel->$selectedModelName->find('first',array(
				'conditions'=>array($model.'.id'=>$this->request->params['named']['id']),
				'fields'=>array($model.'.id',$model.'.'.$this->request->params['pass'][1]),
				'recursive'=>-1));
			if($rec){
				$this->set('record_value',$rec[$model][$this->request->params['pass'][1]]);
			}
		}catch(Exception $e){
		}
		
		$this->set('selectedModelName',$selectedModelName);
		$thisModel = $this->modelClass;
		$selectedModel = $this->$thisModel->belongsTo[$model];
		$model = $selectedModel['className'];
		$this->loadModel($model);
		$fields = $this->$model->schema();
		$fieldOnForm = $selectedModel['foreignKey'];
		$fieldDetails = $fields[$fieldTobeChanged];
		$tableName = $this->$model->useTable;		
		// check if model exists in custome table
		$this->loadModel('CustomTable');
		$table = Inflector::underscore($selectedModel['className']);
		$this->loadModel('CustomTable');
		$customTable = $this->CustomTable->find('first',array('recursive'=>-1, 'fields'=>array('CustomTable.id','CustomTable.name','CustomTable.fields'), 'conditions'=>array('CustomTable.table_name LIKE '=> $tableName)));		
		if($customTable){			
			$customTableFields = json_decode($customTable['CustomTable']['fields'],true);
			if($fieldDetails['type'] == 'boolean'){
				// if field is publish
				if($fieldTobeChanged == 'publish'){
					$this->set('type','checkbox');
				}else{					
				}							
				// try and get values from model
			}
			if($fieldDetails['type'] == 'integer' && $fieldDetails['length'] == null ){
				if($customTable){
					foreach($customTableFields as $customTableField){
						if($customTableField['field_name'] == $fieldTobeChanged){
							$values = explode(',',$customTableField['csvoptions']);
							$this->set('values',$values);
						}
					}
				}
			}
			if($fieldDetails['type'] == 'string' && $fieldDetails['length'] == 36 ){
				foreach($customTableFields as $customTableField){
					if($customTableField['field_name'] == $fieldTobeChanged){
						$newModel = Inflector::classify($customTableField['linked_to']);
						$this->loadModel($newModel);
						$options = $this->$newModel->find('list');
						$this->set('values',$options);
					}else{
					}
				}		
			}			
		}else{			
			$field_details = json_decode(base64_decode($this->request->params['named']['field_details']),true);
			$findModel = base64_decode($field_details['field_label']);
			$newModel = $this->$thisModel->belongsTo[$findModel]['className'];
			$this->loadModel($newModel);			
			$fieldSchema = $this->$newModel->schema();
			$field_details = $fieldSchema[$fieldTobeChanged];
			if($fieldDetails['type'] == 'boolean'){		
				// if field is publish
				if($fieldTobeChanged == 'publish'){
					$this->set('type','checkbox');
				}else{					
				}
				// try and get values from model
			}
			if($field_details['type'] == 'integer' && ($field_details['length'] == null || $field_details['length'] == 1 || $field_details['length'] == 11)){
				//check in $customArray in Model
				$fieldDetails['length'] = 1;
				$customArray = $this->$newModel->customArray;
				if($customArray[Inflector::pluralize(Inflector::variable($fieldTobeChanged))]){
					$values = $customArray[Inflector::pluralize(Inflector::variable($fieldTobeChanged))];
					$this->set('values',$values);
				}
			}
			if($field_details['type'] == 'string' && $field_details['length'] == 36){
				foreach($this->$newModel->belongsTo as $m => $d){
					if($d['foreignKey'] == $fieldTobeChanged){
						$newClass = $d['className'];
						$this->loadModel($newClass);
						$options = $this->$newClass->find('list');
						$this->set('values',$options);
					}
				}
			}
		}
		$this->set('model',$model);
		$this->set('label',$label);
		$this->set('fieldTobeChanged',$fieldTobeChanged);
		$this->set('fieldDetails',$fieldDetails);
		$this->set('schema',$this->$model->schema());
		$this->render('/Elements/field_fetch');
	}

	public function _generate_onlyoffice_pdf($url = null,$filetype = null,$outputtype = null, $password = null, $title = null,$record_id = null,$cover = null,$attach_cover = null){
		$this->set('addwatermark',true);		
		$path = Configure::read('OnlyofficeConversionApi'). '/ConvertService.ashx';
		$key = $this->_generate_onlyoffice_key($record_id . date('Ymdhis'));
		$payload = array(
			'async'=>false,
			'url'=>$url,
			'outputtype'=>$outputtype,
			'filetype'=>$filetype,
			'title'=>$title,
			'key'=>$key,			
		);
		$token = $this->jwtencode(json_encode($payload));
		$arr = [
			'async'=>false,
			'url'=>$url,
			'outputtype'=>$outputtype,
			'filetype'=>$filetype,
			'title'=>$title,
			'key'=>$key,			     
		];
		// add header token
		$headerToken = "";
		$jwtHeader = Configure::read('onlyofficesecret');
		$arr["token"] = $this->generateJWT($arr);	    
		$data = json_encode($arr);	
		// request parameters
		$opts = array('http' => array(
			'method'  => 'POST',
			'timeout' => 30,
			'header'=> "Content-type: application/json\r\n" . 
			"Accept: application/json\r\n" .
			(empty($headerToken) ? "" : $jwtHeader.": Bearer ".$arr['token']."\r\n"),
			'content' => $data
		));
		$context = stream_context_create($opts);
		$response_data = file_get_contents($path, FALSE, $context);
		$downloadUri = json_decode($response_data,true);
		$downloadUri = $downloadUri['fileUrl'];
		if (file_get_contents($downloadUri) === FALSE) {
			Echo "Error in file conversion";
			exit;		
		} else {	
			$savepath = WWW_ROOT .'files' . DS . 'pdf' . DS . $this->Session->read('User.id') . DS . $record_id;
			if(!file_exists($savepath)){
				$folder = new Folder();
				if ($folder->create($savepath,0777)) {
				} else {
					echo "Folder creation failed";
					exit;
				}
			}
			if($cover == false){
				$new_data = file_get_contents($downloadUri);
				$file_for_save = WWW_ROOT .'files' . DS . 'pdf' . DS . $this->Session->read('User.id') . DS . $record_id . DS .  '-remove-pdf-'. $title . '-' . date('his') .'.'.$outputtype;
				if (file_put_contents($file_for_save, $new_data)) {
					$this->add_password($file_for_save,null,$record_id);
				} else {
				}
			}else{
				$new_data = file_get_contents($downloadUri);
				$file_for_save = WWW_ROOT .'files' . DS . 'pdf' . DS . $this->Session->read('User.id') . DS . $record_id . DS .  'cover-pdf'.'.'.$outputtype;
				if (file_put_contents($file_for_save, $new_data)) {
					unlink(WWW_ROOT .'files' . DS . 'pdf' . DS . $this->Session->read('User.id') . DS . $record_id . DS . 'template.html');
				} else {
				}
			}
		}
	}

	public function add_password($pdf = null, $password = null, $record_id = null){
		$allow = '';
		if($this->request->data){
			if($this->request->data['DocumentDownload']['printing']){
				$allow .= 'printing ';
			}else{
				$blockprint = 'print=n';
			}

			if($this->request->data['DocumentDownload']['degraded_printing']){
				$allow .= 'DegradedPrinting ';
			}else{
				
			}

			if($this->request->data['DocumentDownload']['modify_contents']){
				$allow .= 'ModifyContents ';
			}else{
				
			}

			if($this->request->data['DocumentDownload']['copy_contents']){
				$allow .= 'CopyContents ';
			}else{
				
			}

			if($this->request->data['DocumentDownload']['modify_annotations']){
				$allow .= 'ModifyAnnotations ';
			}else{
				
			}
		}

		if($allow != ''){
			$allowcommand = ' allow ' . $allow;
		}else{
			$allowcommand = '';
		}

		$password = $this->request->data['DocumentDownload']['password'];
		// check if cover pdf exists, if yes, attach it
		$cover = WWW_ROOT .'files' . DS . 'pdf' . DS . $this->Session->read('User.id') . DS . $record_id . DS .  'cover-pdf.pdf';
		if(file_exists($cover)){
			$input = $pdf;
			$newoutput = str_replace('-remove-pdf-', '-add-cover-', $pdf);
			$exec = Configure::read('PDFTkPath') . ' A=' .$cover .' B=' .$input.  ' cat A B output '. $newoutput .'';
			exec($exec);
			$input = $pdf;
			$output = str_replace('-add-cover-', '', $newoutput);
			$output = str_replace($record_id, $this->request->params['named']['id'],$output);
			$sign = $this->_sign_to_pdf($this->request->data['DocumentDownload']['signature'],$record_id,$this->request->data['DocumentDownload']['font_face'],$this->request->data['DocumentDownload']['font_size']);
			if($password && $password != ''){
				$ownerpass = $password . '-owner';
				$exec = Configure::read('PDFTkPath') . ' ' .$newoutput .' multistamp ' .$sign.  ' output '. $output . ' user_pw '. $password .' owner_pw '.$ownerpass.' ' . $allowcommand;
			}else{
				$exec = Configure::read('PDFTkPath') . ' ' .$newoutput .' multistamp ' .$sign.  ' output '. $output .' ' . $allowcommand;
			}
			exec($exec);
			unlink($newoutput);
			unlink($cover);
			unlink($input);
			unlink($sign);
		}else{
			$input = $pdf;
			$output = str_replace('-remove-pdf-', '', $pdf);
			$output = str_replace($record_id, $this->request->params['named']['id'],$output);
			$sign = $this->_sign_to_pdf($this->request->data['DocumentDownload']['signature'],$record_id,$this->request->data['DocumentDownload']['font_face'],$this->request->data['DocumentDownload']['font_size']);			
			if($password && $password != ''){
				$ownerpass = $password . '-owner';
				$exec = Configure::read('PDFTkPath') . ' ' .$input .' multistamp ' .$sign.  ' output '. $output . ' user_pw '.$password.' owner_pw '.$ownerpass.' ' . $allowcommand;
			}else{
				$exec = Configure::read('PDFTkPath') . ' ' .$input .' multistamp ' .$sign.  ' output '. $output .' '. $allowcommand;
			}
			exec($exec);
			unlink($input);
			unlink($sign);
		}
	}

	public function _sign_to_pdf($sign = null,$record_id = null,$font_face = null, $font_size = null){
		if(!$font_size){
			$font_size = '6px';
		}
		$CakePdf = new CakePdf(array(
			'options' => array(
				'print-media-type' => false,
				'outline' => false,
				'dpi' => 360,
				'outline'=>true,
				'outline-depth'=>2,
				'enable-local-file-access'=>true,				
			),
			'margin' => array(
				'bottom' => 0,
				'left' => 0,
				'right' => 0,
				'top' => 0
			),
		));

		// get document details
		$this->loadModel('QcDocument');
		$qcDocument = $this->QcDocument->find('first',
			array(
				'conditions'=>array('QcDocument.id'=>$record_id),
				'fields'=>array('QcDocument.id','QcDocument.it_categories','QcDocument.document_status'),
				'recursive'=>-1));
		if($qcDocument){
			$category = $this->QcDocument->customArray['itCategories'][$qcDocument['QcDocument']['it_categories']];
			$status = $this->QcDocument->customArray['documentStatuses'][$qcDocument['QcDocument']['document_status']];			
			$this->set('category',$category);
			$this->set('status',$status);
		}
		$this->set('sign',$sign);
		$CakePdf->template('sign', 'sign');
		$CakePdf->viewVars($this->viewVars);		
		$path = WWW_ROOT .'files'. DS . 'pdf' . DS .$this->Session->read('User.id') . DS . $record_id;
		try{
			$dir = WWW_ROOT .'files'. DS . 'pdf' .DS . $this->Session->read('User.id'). DS . $record_id;
			if(!file_exists($dir)){
				mkdir($dir);
			}
			if(!file_exists($path)){
				mkdir($path);
			}
			chmod($dir,0777);
			chmod($path,0777);
		}catch(Exception $e){
			echo "Path creation failed";
			exit;
		}
		$pagecontentfilename = 'signpdf';
		$pdf = $CakePdf->custom_write($path,$path . DS . $pagecontentfilename.'.pdf');
		$pdf = $path . DS . $pagecontentfilename.'.pdf';
		$pagecontentfilename = $path . DS . $pagecontentfilename.'-.pdf';
		if(!$qcDocument){
			$qcDocument = $this->viewVars['qcDocument'];
		}		
		if($this->viewVars['addwatermark'] == true){
			$output = $path . DS . 'signpdf.pdf';
			$background = WWW_ROOT . 'files' . DS . 'samples' . DS . $qcDocument['QcDocument']['document_status'].'.pdf';
			$exec = Configure::read('PDFTkPath') . ' ' .$pagecontentfilename .' multistamp ' .$background.  ' output '. $output .'';
			exec($exec);
			unlink($pagecontentfilename);
			$this->set('addwatermark',false);
			return $output;
		}else{			
			$output = $path . DS . 'signpdf.pdf';
			$background = WWW_ROOT . 'files' . DS . 'samples' . DS . $qcDocument['QcDocument']['document_status'].'.pdf';
			$exec = Configure::read('PDFTkPath') . ' ' .$pagecontentfilename .'  output '. $output .'';
			exec($exec);
			unlink($pagecontentfilename);
			$this->set('addwatermark',false);
			return $output;
		}
	}

	public function load_process($custom_table_id = null){
		$this->loadModel('CustomTableProcess');
		$processes = $this->CustomTableProcess->find('all',array(
			'fields'=>array('Process.id','Process.name','CustomTableProcess.sequence'),
			'order'=>array('CustomTableProcess.sequence'=>'ASC'), 
			'conditions'=>array('CustomTableProcess.custom_table_id'=>$custom_table_id)));
		$this->set('processes',$processes);
		$this->render('/Elements/load_process');
	}

	public function _write_to_file($folder = null, $file = null, $content = null){
		if($content){
			chmod($folder,0777);
			$fp = fopen($file, 'w');
			fwrite($fp, $content);
			fclose($fp);
		}
	}

	// M9 signature protection (staging, 20 Oct 2026).
	// A signed field (prepared_by, approved_by, issued_by, and custom-table fields marked add_signature) is accepted on save only when
	// it is the logged-in user's own employee id, the value already stored on the record, or an employee who verified their own password
	// in this session within the last 10 minutes; each verification is used once. Verification attempts are rate limited (5 failures per
	// signer, 10 per logged-in user, 15-minute window) and get one uniform failure response. Events go to tmp/logs/m9_signature.log
	// (no password values). get_signatures returns an image only for the user's own employee id or for internal rendering.
	public function _m9_sig_log($event, $detail = array()) {
		$line = date('Y-m-d H:i:s')."\t".$event."\tuser=".$this->Session->read('User.id')."\temployee=".$this->Session->read('User.employee_id')
			."\tip=".$this->request->clientIp()."\t".$this->request->params['controller'].'/'.$this->action;
		foreach ($detail as $k => $v) $line .= "\t".$k.'='.(is_scalar($v) ? $v : json_encode($v));
		@file_put_contents(LOGS.'m9_signature.log', $line."\n", FILE_APPEND | LOCK_EX);
	}

	// Failure counters in small locked files under tmp/m9_sig (one per key, 15-minute window from the last failure).
	// Deliberately independent of the CakePHP cache configuration.
	public function _m9_sig_counter($key, $increment = false) {
		$dir = TMP.'m9_sig';
		if (!is_dir($dir)) @mkdir($dir, 0700, true);
		$fp = @fopen($dir.DS.md5($key), 'c+');
		if (!$fp) return 0;
		flock($fp, LOCK_EX);
		$d = json_decode((string)stream_get_contents($fp), true);
		$n = (is_array($d) && isset($d['t']) && (time() - (int)$d['t']) <= 900) ? (int)$d['n'] : 0;
		if ($increment) { $n++; ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode(array('n' => $n, 't' => time()))); fflush($fp); }
		flock($fp, LOCK_UN); fclose($fp);
		return $n;
	}

	public function _m9_sig_locked($signer) {
		return $this->_m9_sig_counter('signer:'.$signer) >= 5 || $this->_m9_sig_counter('user:'.$this->Session->read('User.id')) >= 10;
	}

	public function _m9_sig_fail($signer) {
		$this->_m9_sig_counter('signer:'.$signer, true);
		$this->_m9_sig_counter('user:'.$this->Session->read('User.id'), true);
	}

	public function _m9_password_matches($stored, $plain) {
		$stored = trim((string)$stored);
		if ($stored === '' || !is_string($plain) || $plain === '') return false;
		return hash_equals($stored, trim(Security::hash($plain, 'md5', true)));
	}

	public function _m9_signed_fields($model) {
		$f = array();
		foreach (array('prepared_by', 'approved_by', 'issued_by') as $n) {
			if (isset($this->request->data[$model][$n])) $f[$model.'.'.$n] = $this->request->data[$model][$n];
			if (isset($this->request->data['Approval'][$model][$n])) $f['Approval.'.$model.'.'.$n] = $this->request->data['Approval'][$model][$n];
		}
		if (!empty($this->request->data[$model]) && is_array($this->request->data[$model]) && isset($this->$model) && is_object($this->$model) && !empty($this->$model->useTable)) {
			$this->loadModel('CustomTable');
			$ct = $this->CustomTable->find('first', array('recursive' => -1, 'fields' => array('CustomTable.fields'), 'conditions' => array('CustomTable.table_name' => $this->$model->useTable)));
			$defs = $ct ? json_decode($ct['CustomTable']['fields'], true) : null;
			if (is_array($defs)) foreach ($defs as $d) {
				if (is_array($d) && !empty($d['add_signature']) && !empty($d['field_name']) && isset($this->request->data[$model][$d['field_name']])) {
					$f[$model.'.'.$d['field_name']] = $this->request->data[$model][$d['field_name']];
				}
			}
		}
		return $f;
	}

	public function _m9_signature_guard() {
		if (!($this->request->is('post') || $this->request->is('put')) || empty($this->request->data)) return;
		if (in_array($this->action, array('addsignature', 'checkpass', 'login', 'logout'))) return;
		$model = $this->modelClass;
		try { $fields = $this->_m9_signed_fields($model); } catch (Exception $e) { $fields = array(); }
		if (!$fields) return;
		$me = (string)$this->Session->read('User.employee_id');
		$ver = (array)$this->Session->read('M9Sign');
		$existing = null; $use = array(); $refused = array();
		foreach ($fields as $name => $val) {
			if (!is_scalar($val)) { $refused[$name] = 'non-scalar'; continue; }
			$val = trim((string)$val);
			if ($val === '' || $val === '-1' || ($me !== '' && $val === $me)) continue;
			if ($existing === null) {
				$existing = array();
				$id = isset($this->request->data[$model]['id']) ? $this->request->data[$model]['id'] : (isset($this->request->params['pass'][0]) ? $this->request->params['pass'][0] : null);
				if ($id) { try { $r = $this->$model->find('first', array('recursive' => -1, 'conditions' => array($model.'.id' => $id))); if ($r) $existing = $r[$model]; } catch (Exception $e) {} }
			}
			$col = substr($name, strrpos($name, '.') + 1);
			if (isset($existing[$col]) && (string)$existing[$col] === $val) continue;
			if (isset($ver[$val]['t']) && (time() - (int)$ver[$val]['t']) <= 600) { $use[$val] = 1; continue; }
			$refused[$name] = $val;
		}
		if ($refused) {
			$this->_m9_sig_log('save_refused', array('fields' => $refused));
			if ($this->request->is('ajax')) { echo 'Signature not verified'; exit; }
			$this->Session->setFlash(__('The signature could not be verified. The selected person must enter their own password before the record is saved with their signature.'), 'default', array('class' => 'alert alert-danger'));
			$this->redirect($this->referer());
		}
		if ($use) {
			foreach (array_keys($use) as $e) { unset($ver[$e]); $this->_m9_sig_log('save_with_verified_signature', array('signer' => $e)); }
			$this->Session->write('M9Sign', $ver);
		}
	}

	public function addsignature($employee_id = null, $fieldid = null){
		$this->layout = 'ajax';
		if ($this->request->is('post') || $this->request->is('put')) {
			$signer = isset($this->request->data['AddSignature']['employee_id']) ? trim((string)$this->request->data['AddSignature']['employee_id']) : '';
			$pw = isset($this->request->data['AddSignature']['user_password']) ? $this->request->data['AddSignature']['user_password'] : '';
			if ($signer === '' || !is_string($pw) || $pw === '') {
				$response = 'invalid';
			} elseif ($this->_m9_sig_locked($signer)) {
				$response = 'invalid';
				$this->_m9_sig_log('verify_locked', array('signer' => $signer));
			} else {
				$this->loadModel('User');
				$user = $this->User->find('first', array(
					'fields' => array('User.id', 'User.password', 'User.employee_id', 'Employee.id', 'Employee.signature'),
					'conditions' => array('User.status' => 1, 'User.soft_delete' => 0, 'User.publish' => 1, 'User.employee_id' => $signer)));
				if ($user && $this->_m9_password_matches($user['User']['password'], $pw)) {
					$img = WWW_ROOT. DS. 'img'. DS . $this->Session->read('User.company_id'). DS .'signature'. DS. $user['Employee']['id']. DS. 'sign.png';
					if (file_exists($img) || $user['Employee']['signature']) {
						$ver = (array)$this->Session->read('M9Sign');
						foreach ($ver as $k => $v) if (!isset($v['t']) || time() - (int)$v['t'] > 600) unset($ver[$k]);
						$ver[$signer] = array('t' => time());
						$this->Session->write('M9Sign', $ver);
						$response = 'Proceed';
						$this->_m9_sig_log('verify_ok', array('signer' => $signer));
					} else {
						$response = 'Signature not available';
						$this->_m9_sig_log('verify_ok_no_signature', array('signer' => $signer));
					}
				} else {
					// one response for unknown signer and wrong password
					$response = 'Wrong password';
					$this->_m9_sig_fail($signer);
					$this->_m9_sig_log('verify_failed', array('signer' => $signer));
				}
			}
			echo trim($response);
			exit;
		}else{
			if($employee_id){
				$this->loadModel('Employee');
				$employee = $this->Employee->find(
						'first',array(
							'conditions'=>array('Employee.id'=>$employee_id),
							'recursive'=>-1,
							'fields'=>array('Employee.id','Employee.name','Employee.signature'))
				);
				
				if($employee){
					$this->set('employee',$employee);
				}
			}
			$this->set(array('fieldid'=>$fieldid,'employee_id'=>$employee_id));
			$this->render('/Elements/addsignature');			
		}
		
	}

	public function checkpass(){
		$this->autoRender = false;
		$pw = isset($this->request->data['AddSignature']['user_password']) ? $this->request->data['AddSignature']['user_password'] : '';
		if (!is_string($pw) || $pw === '') return 'wrong data';
		$me = (string)$this->Session->read('User.employee_id');
		if ($this->_m9_sig_locked($me)) { $this->_m9_sig_log('checkpass_locked'); return 'Wrong password'; }
		$this->loadModel('User');
		$user = $this->User->find('first', array('conditions' => array('User.status' => 1, 'User.soft_delete' => 0, 'User.publish' => 1, 'User.username' => $this->Session->read('User.username'))));
		if ($user && $this->_m9_password_matches($user['User']['password'], $pw)) return 'Proceed';
		$this->_m9_sig_fail($me);
		$this->_m9_sig_log('checkpass_failed');
		return 'Wrong password';
	}

	public function _custom_table_short_info($custom_table_id = null){
		$this->loadModel('CustomTable');
		$customTable = $this->CustomTable->find('first',
			array(
				'recursive'=>-1,
				'fields'=>array('CustomTable.id','CustomTable.table_name','CustomTable.name'),
				'conditions'=>array('CustomTable.id'=>$custom_table_id)));
		if($customTable){
			return $customTable;
		}else{
			return false;
		}
		exit;
	}
	public function field_render_json($field = null,$record = null, $model = null, $custom_table_id = null){
		$this->loadModel('CustomTable');
		$customTable = $this->CustomTable->find('first',array(
			'recursive'=>-1,
			'fields'=>array('CustomTable.id','CustomTable.fields'),
			'conditions'=>array('CustomTable.id'=>$custom_table_id)
		));

		if($customTable){
			$fields = json_decode($customTable['CustomTable']['fields'],true);
			foreach($fields as $fld){
				if($fld['linked_to_field_name'] == $field){
					$this->loadModel($model);
					$recordresult = $this->$model->find('first',array(
						'recursive'=>-1,
						'fields'=>array($model.'.id',$model.'.'.$field),
						'conditions'=>array($model.'.id'=>$record)
					));
					return $this->field_render($fld,$recordresult,$model);
				}
			}
		}
		
	}
	public function field_render($field = null,$record = null, $model = null){
		$text = array();
		switch ($field['display_type']) {
			case 0: // text/ phone/ email/ textarea/ date / datetime / number / float
				switch ($field['data_type']){
					case "text":
						$text['label'] = base64_decode($field['field_label']);
						$text['value'] = $record[$model][$field['field_name']];
						$text['name'] = $field['field_name'];
					break;

					case "phone":
						$text['label'] = base64_decode($field['field_label']);
						$text['value'] = $record[$model][$field['field_name']];
						$text['name'] = $field['field_name'];
					break;

					case "email":
						$text['label'] = base64_decode($field['field_label']);
						$text['value'] = $record[$model][$field['field_name']];
						$text['name'] = $field['field_name'];
					break;

					case "textarea":
						$text['label'] = base64_decode($field['field_label']);
						$text['value'] = $record[$model][$field['field_name']];
						$text['name'] = $field['field_name'];
					break;

					case "date":
						$text['label'] = base64_decode($field['field_label']);
						$text['value'] = date(Configure::read('dateFormat'),strtotime($record[$model][$field['field_name']]));
						$text['name'] = $field['field_name'];
					break;

					case "datetime":
						$text['label'] = base64_decode($field['field_label']);
						$text['value'] = date(Configure::read('dateTimeFormat'),strtotime($record[$model][$field['field_name']]));
						$text['name'] = $field['field_name'];
					break;

					case "number":
						$text['label'] = base64_decode($field['field_label']);
						$text['value'] = $record[$model][$field['field_name']];
						$text['name'] = $field['field_name'];
					break;

					case "float":
						$text['label'] = base64_decode($field['field_label']);
						$text['value'] = $record[$model][$field['field_name']];
						$text['name'] = $field['field_name'];
					break;
				}

			break;

			case 1: // radio
					$csvoptions = explode(',',$field['csvoptions']);
					$text['label'] = base64_decode($field['field_label']);
					$text['value'] = $csvoptions[$record[$model][$field['field_name']]];
					$text['name'] = $field['field_name'];
			break;

			case 2: // checkox/ radio !! ISSUE
					$csvoptions = explode(',',$field['csvoptions']);
					$text['label'] = base64_decode($field['field_label']);
					$text['value'] = $csvoptions[$record[$model][$field['field_name']]];
					$text['name'] = $field['field_name'];
			break;

			case 3: // dropdown-s
					$belongsTo = $this->$model->belongsTo;
					$signature = '';
					foreach($belongsTo as $modelname => $fieldDetails){
						if($fieldDetails['foreignKey'] == $field['field_name']){
							$this->loadModel($modelname);
							$displayField = $this->$modelname->displayField;
							if($field['add_signature'] == 1){
								$signature = $this->_fetch_signature($record[$model][$field['field_name']]);
							}
							$text['label'] = base64_decode($field['field_label']);
							$text['value'] = $signature . "" .$record[$modelname][$displayField];
							$text['name'] = $field['field_name'];
						}
					}
			break;

			case 4: // dropdown-m
					if(!empty($record[$model][$field['field_name']])){
						$values = json_decode($record[$model][$field['field_name']],true);	
						foreach ($values as $value){
							$result .= $this->_fetch_record(Inflector::Classify($field['linked_to']), 'id', null, null, $value) .', ';
						}

					}
					$text['label'] = base64_decode($field['field_label']);
					$text['value'] = $result;
					$text['name'] = $field['field_name'];
			break;

			case 5:
					$text['label'] = base64_decode($field['field_label']);
					$text['value'] = $record[$model][$field['field_name']];
					$text['name'] = $field['field_name'];
			break;

			case 6: // file
					$f = '$record["'.$model.'"]["'.$field['field_name'].'"]';
					$file = json_decode($record[$model][$field['field_name']],true);
					$file = $file['name'] .'<br /><small>Available for download from the application.</small>';
					$text['label'] = base64_decode($field['field_label']);
					$text['value'] = $file;	
					$text['name'] = $field['field_name'];
			break;

			case 7: // comments
					$text['label'] = base64_decode($field['field_label']);
					$text['value'] = $record[$model][$field['field_name']];
					$text['name'] = $field['field_name'];
			break;

			case 9: // belongs2		
				if(isset($field['csvoptions'])){
					$csvoptions = explode(',',$field['csvoptions']);
					$text['label'] = base64_decode($field['field_label']);
					$text['value'] = $csvoptions[$record[$model][$field['field_name']]];
					$text['name'] = $field['field_name'];	
				}else{
					if($this->$model->customArray[Inflector::pluralize(Inflector::variable($field['linked_to_field_name']))]){	
					 	$text['label'] = base64_decode($field['field_label']);
						$text['value'] = $this->$model->customArray[Inflector::pluralize(Inflector::variable($field['linked_to_field_name']))][$record[$model][$field['linked_to_field_name']]];
						$text['name'] = $field['linked_to_field_name'];
					}else{
						$text['label'] = base64_decode($field['field_label']);
						$text['name'] = $field['linked_to_field_name'];
						// $belongsTo = $this->$model->belongsTo;
						$text['value'] = $record[$field['linked_model']][$field['linked_to_field_name']];						
					}
					
					break;
				}
		}		
		return $text;
		exit;
	}

	public function _sort(){
		$model = Inflector::Classify($this->request->controller);
		try{
			$this->loadModel($model);
		}catch (Exception $e){
			return false;
		}

		$belongsTos = $this->$model->belongsTo;
		$skiparray = array('SystemTable','Company','BranchIds','DepartmentIds','CustomTable','CreatedBy','ModifiedBy','QcDocument');
		foreach($belongsTos as $name => $belongsTo){
			if(!in_array($name, $skiparray)){
				$display[$name] = $belongsTo['foreignKey'];
			}
		}
		$this->set('sortingFields',$display);		
	}

	public function return_user_list(){
		$this->autoRender = false;
		$newoptions = '';
		$users = $this->_get_user_list();
		unset($users[$this->Session->read('User.id')]);
		foreach($users as $userid => $user){
			$newoptions .= '<option value="'.$userid.'">'.$user.'</option>';
		}
		echo $newoptions;
		exit;		
	}

	public function _html_cleanup($html = null){
	    $output = preg_replace('/(<[^>]+) style=".*?"/i', '$1', $html);
	    $output = preg_replace('/cellspacing=".*?"/i', ' cellspacing="1"', $output);
	    $output = preg_replace('/cellpadding=".*?"/i', ' cellpadding="3"', $output);
	    $output = preg_replace('/border=".*?"/i', ' border="1"', $output);
	    $output = str_replace('<span>', '', $output);
	    $output = str_replace('</span>', '', $output);
	    $output = str_replace('<td width="', '<td alt="', $output);
	    $style = '<style>table{width:100%;}</style>';
	    $output = str_replace('</head>',$style.'</head>',$output);
	    $output = str_replace('&quot;','"',$output);
	    return $output;
	}

	public function return_ue_name($id = null){
		$this->loadModel('User');
		if($id){
			$user = $this->User->find('first',array(
				'recursive'=>-1,
				'fields'=>array('User.name'),
				'conditions'=>array('User.id'=>$id)
			));

			if($user){
				return $user['User']['name'];
			}else{
				return 'User not found';
			}
		}
		return 'User not found';
	}

	public function _get_pre_user($employee_id = null){
		$this->loadModel('User');
		$user = $this->User->find('first',array(
			'recursive'=>-1,
			'fields'=>array('User.id','User.employee_id'),
			'conditions'=>array('User.employee_id'=>$employee_id)
		));

		if($user){
			return $user['User']['id'];
		}else{
			return null;
		}
	}


	public function fetch_last_value($field = null,$lp = null){
		$this->autoRender = false;
		$model = $this->modelClass;
		$this->loadModel($model);
		$record = $this->$model->find('first',array('fields'=>array($model.'.'.$field),'order'=>array($model.'.'.$field => 'DESC')));
		if($record){
			$lastvalue = $record[$model][$field];	
			if($lp){
				$lastvalue = ltrim($lastvalue,'0');				
			}
			$lastvalue = $lastvalue + 1;
		}else{
			$lastvalue = 0;
		}

		if($lp){
			$lastvalue = str_pad($lastvalue,$lp,0, STR_PAD_LEFT);
		}
		return $lastvalue;	
	}

	public function _fetch_approval_steps($custom_table_id = null){
		$this->loadModel('ApprovalProcess');

		if($this->request->params['named']['custom_table_id']){
			$approvalProcess = $this->ApprovalProcess->find('first',array(
				'conditions'=>array(
					// 'OR'=>array(
					// 	'CustomTable.custom_table_id'=> null,
					// 	'CustomTable.custom_table_id'=> '',
					// 	'CustomTable.custom_table_id'=> 'NULL',
					// ),					
					'ApprovalProcess.applicable_to LIKE '=>'%'.$this->request->params['named']['custom_table_id'].'%')
				)
			);			
		}else if($this->request->controller == 'qc_documents'){
			$approvalProcess = $this->ApprovalProcess->find('first',array('conditions'=>array('ApprovalProcess.applicable_to LIKE '=> '%'.'qc_documents' .'%')));
		}
		if($approvalProcess){
			$this->set('approvalProcess',$approvalProcess);
			$currentStep = $this->_current_step($approvalProcess);
			$this->set('currentStep',$currentStep[0]);
			$this->set('approversList',$currentStep[1]);
			$modelClass = $this->modelClass;
			
			$fields = $this->$modelClass->schema();
			if(array_key_exists('approval_step_id', $fields)){ 			
			}else{
				$updatesql = 'ALTER TABLE `'.$this->request->controller.'` ADD `approval_step_id` VARCHAR(36) NULL DEFAULT NULL AFTER `status_user_id`;';
				$this->$modelClass->query($updatesql);

			}

			return $approvalProcess;
		}		
		 
	}

	public function _current_step($approvalProcess = null){
		$this->loadModel('ApprovalStep');
		if($this->action == 'add'){
			$currentStep = $this->ApprovalStep->find('first',array('conditions'=>array('ApprovalStep.id'=>$approvalProcess['ApprovalStep'][0]['id'])));
			$this->set('currentStep',$currentStep);
			$approversList = $this->_get_approver_lists($this->Session->read('User.id'),$currentStep['ApprovalStep']);			
			$this->set('approversList',$approversList);
		}else{			
			$model = $this->modelClass;
			if($this->request->data[$model]['approval_step_id']){
				$currentStep = $this->ApprovalStep->find('first',array('conditions'=>array('ApprovalStep.id'=>$this->request->data[$model]['approval_step_id'])));			
				$approversList = $this->_get_approver_lists($this->Session->read('User.id'),$currentStep['ApprovalStep']);
				$this->set('approversList',$approversList);
			}else if($this->viewVars[Inflector::variable($this->modelClass)][$this->modelClass]['approval_step_id']){
				$currentStep = $this->ApprovalStep->find('first',array('conditions'=>array('ApprovalStep.id'=>$this->viewVars[Inflector::variable($this->modelClass)][$this->modelClass]['approval_step_id'])));
				$approversList = $this->_get_approver_lists($this->Session->read('User.id'),$currentStep['ApprovalStep']);
				$this->set('approversList',$approversList);
			}
		}
		if(!$approversList){			
			$skiparray = array('qc_documents','custom_tables');
			
			// if(!in_array($this->request->controller, $skiparray)){
			$actionarray = array('add','edit','view');
				if(in_array($this->action, $actionarray) ) {
					// $this->Session->setFlash(__('Contact your admin. This process step does not have any approvers. Please add approvers first before you proceed.'));
					// $this->redirect(array('controller'=>'approval_processes', 'action' => 'view', $currentStep['ApprovalStep']['approval_process_id'],'timestamp'=>date('Ymdhis'))); 	
				}
				
			// }			
		}else{
			
		}	

		return array($currentStep,$approversList);	
		
	}

	public function _get_approver_lists($creator = null,$approvalSteps = null) {
		if($approvalSteps['send_to_reviwers'] == 1){ // reviewer
			$ucon = array(
				'User.is_view_all'=>1,								
			);
		}else if($approvalSteps['send_to_publishers'] == 1){ // publishers
			$ucon = array(
				'User.is_publisher'=>1,				
			);
		}else if($approvalSteps['send_to_department_hod'] == 1){ // hods
			$ucon = array(
				'User.is_hod'=>1,				
			);
		}else if($approvalSteps['send_to_approvers'] == 1){ // hods
			$ucon = array(
				'User.is_approver'=>1,				
			);
		}else if($approvalSteps['send_to_admins'] == 1){
			$ucon = array('User.is_mr'=>1); // admin
		}else if($approvalSteps['send_to_users'] == 1){ // users
			$ucon = array('User.id'=>json_decode($autoApprovals['ApprovalStep']['send_to_users'],true));
		}

		if($approvalSteps['ignore_department'] == 1){
			$deptCon = array('User.department_id'=>$this->Session->read('User.department_id'));
		}else{
			$deptCon = array();
		}

		if($approvalSteps['ignore_branch'] == 1){
			$bCon = array('User.branch_id'=>$this->Session->read('User.branch_id'));
		}else{
			$bCon = array();
		}

			

		$this->loadModel('User');
		$this->User->virtualFields = array(
			'is_hod' => 'select `is_hod` from employees where employees.id LIKE User.employee_id LIMIT 1'
		);
		$approversList = $this->User->find('list', array(
			'fields'=>array('User.id','User.name'),
			'conditions' => array(				
				$ucon,
				$deptCon,
				$bCon,
				'User.id !=' => $creator			
		)));		
		return $approversList;	
	}

	public function _isBase64Encoded($str) {
        if (!is_string($str)) {
            return false;
        }
        $decoded = base64_decode($str, true);
        if ($decoded === false) {
            return false;
        }
        return base64_encode($decoded) === $str;
    }	

}
