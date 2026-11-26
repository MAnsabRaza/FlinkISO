<?php
App::uses('AppController', 'Controller');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');
// Owner's fifth review (16 Nov 2026), decision 392: thrown inside a form-builder submit; the submit is then undone.
if (!class_exists('M9BuilderAbort', false)) { class M9BuilderAbort extends Exception { } }
/**
 * CustomTables Controller
 *
 * @property CustomTable $CustomTable
 * @property PaginatorComponent $Paginator
 */
class CustomTablesController extends AppController {
    /**
     * Components
     *
     * @var array
     */
    public $components = array('Paginator','AvailableForms','CreateModel', 'CreateView', 'CreateChildModel');

    public $reserved_fields = array("id", "prepared_by", "approved_by", "created", "modified", "sr_no", "qc_document_id", "file_id", "file_key" ,"created_by" ,"modified_by" ,"status_user_id" ,"record_status" ,"branchid" ,"departmentid" ,"company_id" ,"soft_delete" ,"process_id","publish","custom_table_id");     
    
    public function _get_system_table_id() {
        $this->loadModel('SystemTable');
        $this->SystemTable->recursive = - 1;
        $systemTableId = $this->SystemTable->find('first', array('conditions' => array('SystemTable.system_name' => $this->request->params['controller'])));
        return $systemTableId['SystemTable']['id'];
    }
    public function _commons($creator = null) {
        if ($this->action == 'view' || $this->action == 'recreate') $this->set('approvals', $this->get_approvals());

        $this->set('branches', $this->_get_branch_list());
        $this->set('departments', $this->_get_department_list());
        $this->set('designations', $this->_get_designation_list());
        $this->set('usernames', $this->_get_usernames());

        $preparedBies = $approvedBies = $this->CustomTable->PreparedBy->find('list', array('conditions' => array('PreparedBy.publish' => 1, 'PreparedBy.soft_delete' => 0)));
        $createdBies = $modifiedBies = $this->CustomTable->CreatedBy->find('list', array('conditions' => array('CreatedBy.publish' => 1, 'CreatedBy.soft_delete' => 0)));
        $qcDocuments = $this->CustomTable->QcDocument->find('list', array('conditions' => array('QcDocument.publish' => 1, 'QcDocument.soft_delete' => 0)));
        $users = $this->_get_user_list();
        $approvers = $this->_get_approver_list();
        $this->set(compact('companies', 'preparedBies', 'approvedBies', 'createdBies', 'modifiedBies','users','approvers'));
        $count = $this->CustomTable->find('count');
        $publish = $this->CustomTable->find('count', array('conditions' => array('CustomTable.publish' => 1)));
        $unpublish = $this->CustomTable->find('count', array('conditions' => array('CustomTable.publish' => 0)));
        $fieldTypes = $this->CustomTable->customArray['fieldTypes'];
        $displayTypes = $this->CustomTable->customArray['displayTypes'];
        //get all tables
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

        $customArray = $this->CustomTable->customArray;
        $dataTypes = array('text','phone','email','textarea','checkbox','radio','dropdown-s','dropdown-m','date','datetime','number','file');
        $bootstrapSizes = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);
        $this->set(compact('count', 'publish', 'unpublish', 'fieldTypes', 'bootstrapSizes', 'linkedTos', 'displayTypes', 'qcDocuments','dataTypes','customArray'));
        // add creator
        if ($this->request->params['named']['approval_id']) {
            $this->get_approval($this->request->params['named']['approval_id'], $creator);
            $this->_get_approval_comnments($this->request->params['named']['approval_id'], $creator);
        }
        $this->_get_approver_list($creator);
        $processes = $this->CustomTable->Process->find('list', array('recursive' => -1, 'conditions' => array()));
        $standards = $this->CustomTable->QcDocument->Standard->find('list', array('recursive' => -1, 'conditions' => array('Standard.publish'=>1)));
        $clauses = $this->CustomTable->QcDocument->Standard->Clause->find('list', array('recursive' => -1, 'conditions' => array()));
        $departments = $this->CustomTable->QcDocument->CreatedBy->Department->find('list');
        $branches = $this->CustomTable->QcDocument->CreatedBy->Branch->find('list');
        $schedules = $this->CustomTable->QcDocument->Schedule->find('list');
        $approvalProcesses = $this->CustomTable->ApprovalProcess->find('list');
        $this->set(compact('processes','standards','clauses','departments','branches','schedules','approvalProcesses'));
        $reserved_fields = array("id", "name", "prepared_by", "approved_by", "created", "modified", "sr_no", "qd_document_id", "file_id", "file_key" ,"created_by" ,"modified_by" ,"status_user_id" ,"record_status" ,"branchid" ,"departmentid" ,"company_id" ,"soft_delete" ,"process_id");

    }
    /**
     * index method
     *
     * @return void
     */
    public function index() {
        if($this->request->params['named']['qc_document_id']){
            $accessConditions = array('CustomTable.qc_document_id'=>$this->request->params['named']['qc_document_id']);
        }else{
            $accessConditions = array();
        }        
        $conditions = $this->_check_request();
        if(isset($this->request->params['named']['table_type']) && $this->request->params['named']['table_type'] == 1)$accessConditions[] = array('CustomTable.table_type'=>0,'QcDocument.parent_document_id'=>-1);
        else if($this->request->params['named']['table_type'] == 2)$accessConditions[] = array('CustomTable.table_type'=>1);
        else if($this->request->params['named']['table_type'] == 3)$accessConditions[] = array('CustomTable.table_type'=>2);
        else if($this->request->params['named']['table_type'] == 5)$accessConditions[] = array('QcDocument.parent_document_id !='=>-1);
        else {$accessConditions[] = array('CustomTable.table_type'=>array(0,1));$this->request->params['named']['table_type'] = 4;}
        
        $this->CustomTable->virtualFields = array(
            'linked' => 'select count(*) from `custom_tables` where `custom_tables`.`custom_table_id` LIKE CustomTable.id ',
            'childDoc' => 'select count(*) from `qc_documents` where QcDocument.parent_document_id LIKE `qc_documents`.id ',
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
            '
        );

        if($this->Session->read('User.is_mr') == false){
            $accessConditions[] = array(
                'OR'=>array(
                    'CustomTable.srct >' => 0,
                    'CustomTable.creators LIKE ' => '%'.$this->Session->read('User.id').'%'
                )
                
            );
        }else{
            $accessConditions[] = array();
        }

        if($this->request->params['named']['standard_id']){
            $accessConditions[] = array(
                'QcDocument.standard_id'=>$this->request->params['named']['standard_id']
            );
        }
        $this->paginate = array(
            'order' => array('CustomTable.childDoc'=>'ASC','CustomTable.name' => 'ASC'), 
            'conditions' => array(
                $accessConditions,                
                'OR' => array('ltrim(rtrim(CustomTable.custom_table_id))' => "", 'CustomTable.custom_table_id' => null, 'CustomTable.linked >' => 0)));
        $this->CustomTable->recursive = 0;
        $customTables = $this->paginate();    
        
        $this->set('customTables', $customTables);
        $schedules = $this->CustomTable->QcDocument->Schedule->find('list');
        $customArray = $this->CustomTable->customArray;
        $this->set(array('schedules'=>$schedules,'customArray'=>$customArray));
        $this->_get_count();
        $standards = $this->CustomTable->QcDocument->Standard->find('list', array('recursive' => -1, 'conditions' => array('Standard.publish'=>1)));
        $this->set('standards',$standards);
        // table count
        foreach($standards as $standard_id => $standard_name){
            $cTableCount[$standard_id] = $this->CustomTable->find('count',array('conditions'=>array('QcDocument.standard_id'=>$standard_id)));
        }
        $this->set('cTableCount',$cTableCount);
    }
    
    public function child($custom_table_id = null) {
        $conditions = $this->_check_request();
        $this->paginate = array('order' => array('CustomTable.name' => 'ASC'), 'conditions' => array($conditions, 'CustomTable.custom_table_id' => $this->request->params['named']['custom_table_id']));
        $this->CustomTable->recursive = 0;
        $customTables = $this->paginate();
        $this->set('customTables', $customTables);
        $this->_get_count();
    }
    /**
     * view method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    public function view($id = null) {
        if (!$this->CustomTable->exists($id)) {
            throw new NotFoundException(__('Invalid Table'));
        }

        if($this->Session->read('User.is_mr') == false){
            if($this->request->is('ajax') == false){
                $this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
                $this->redirect(array('controller' => 'users', 'action' => 'access_denied',$n));
            }else{
                echo "<br />You are not authorized to view this form<br /><br />";
                exit;
            }
        }

        $this->CustomTable->virtualFields = array('linked' => 'select count(*) from `custom_tables` where `custom_tables`.`custom_table_id` LIKE CustomTable.id ',);
        $options = array('recursive'=>0, 'conditions' => array('CustomTable.' . $this->CustomTable->primaryKey => $id));
        $customTable = $this->CustomTable->find('first', $options);
        $this->set('customTable', $this->CustomTable->find('first', $options));
        if ($this->_show_approvals()) {
            $this->loadModel('User');
            $this->User->recursive = 0;
            $userids = $this->User->find('list', array('order' => array('User.name' => 'ASC'), 'conditions' => array('User.publish' => 1, 'User.soft_delete' => 0, 'User.is_approver' => 1)));
            $this->set(array('userids' => $userids));
            $this->set(array('showApprovals' => $this->_show_approvals()));
        }

        if($customTable['CustomTable']['created_by'] != $this->Session->read('User.id')){
            $this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
            $this->redirect(array('controller' => 'users', 'action' => 'access_denied',$n));
        }

        if ($this->request->params['named']['approval_id']) {
            $this->redirect(array('action' => 'recreate', $id, 'approval_id' => $this->request->params['named']['approval_id']));
        }
        
        // check if file exists, if not, add from latest qc file
        $qcDocument = $this->_qc_document_header($customTable['CustomTable']['qc_document_id']);        
        $file_type = $qcDocument['QcDocument']['file_type'];
        $file_name = $qcDocument['QcDocument']['title'];
        $document_number = $qcDocument['QcDocument']['document_number'];
        $document_version = $qcDocument['QcDocument']['revision_number'];        
        $file_name = $document_number . '-' . $file_name . '-' . $document_version;
        $file_name = $this->_clean_table_names($file_name);
        $file_name = $file_name . '.' . $file_type;
        $tableFile = WWW_ROOT .  'files' . DS . $this->Session->read('User.company_id') . DS . 'custom_tables' . DS . $id . DS . $file_name;
        if(!file_exists($tableFile)){
            // Owner's fourth review (13 Nov 2026): this page copied the document's file into the form's folder on a GET whenever the
            // form's copy was missing. Opening the page no longer writes any file. When the document's file exists, the page shows a
            // button; the form's creator restores the copy by POST (restore_form_document, session token, administrator).
            $qcfile = WWW_ROOT .  'files' . DS . $this->Session->read('User.company_id') . DS . 'qc_documents' . DS . $qcDocument['QcDocument']['id'] . DS . $file_name;;
            if(file_exists($qcfile)){
                $this->set('m9FormCopyMissing', true);
            }else{
                $this->set('doc_not_found',true);
            }
        }

        // get branch users
        $this->loadModel('User');
        $this->loadModel('Employee');
        $this->Employee->Behaviors->load('Containable');
        $branchUsers = $this->User->find('list',array('conditions'=>array('User.branch_id'=> json_decode($qcDocument['QcDocument']['branches'],true))));
        $departmentUsers = $this->User->find('list',array('conditions'=>array('User.department_id'=> json_decode($qcDocument['QcDocument']['departments'],true))));
        
        $this->Employee->contain('User.id','User.name');
        $dUsers = $this->Employee->find('all',array(
            'fields'=>array('Employee.id','Employee.designation_id'),
            'recursive'=>0,            
            'conditions'=>array('Employee.designation_id'=> json_decode($qcDocument['QcDocument']['designations'],true))));
        if($dUsers){
            foreach($dUsers as $dusers){
                foreach($dusers['User'] as $duser){
                    $designationUsers[$duser['id']] = $duser['name'];
                }
            }
        }

        $this->set('branchUsers','departmentUsers','designationUsers');
        $this->set('qcDocument', $qcDocument);
        $this->set('process', $this->_process_header($customTable['CustomTable']['process_id']));
        $this->_commons($this->Session->read('User.id'));
        // get child 
        $childs = $this->CustomTable->find('all',array('conditions'=>array('CustomTable.custom_table_id'=>$id)));
        $this->set('childs',$childs);
        $customTriggers = $this->CustomTable->CustomTrigger->find('all',array('conditions'=>array('CustomTrigger.custom_table_id'=>$customTable['CustomTable']['id'])));
        $this->set('customTriggers',$customTriggers);
        $key = $this->_generate_onlyoffice_key($customTable['CustomTable']['id'] . date('Ymdhis'));
        $this->set('filekey', $key);
        // M9 review R1 (owner review 12 Nov 2026). FlinkISO saved the form here on every visit: each line above set $updateTable outside
        // its "if", so the save ran whether or not an access list was empty, and rewrote the form's modification time and user on a
        // GET. Opening this page no longer saves anything. A form whose access lists are empty keeps them empty until an administrator
        // sets them with the access form on this page (custom_tables/updateaccess, POST with the session token); the page already
        // shows the document's lists as the suggested values.
    }
    
    public function edit($id = null) {
        if($this->Session->read('User.is_mr') == false){
            $this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
            $this->redirect(array('controller' => 'users', 'action' => 'access_denied',$n));
        }
        $this->redirect(array('action' => 'view', $id));
    }

    public function _make_table_name($qc_doc_id = null,$type = null) {
        if($type == 'qc_documents'){
            $qcDocument = $this->CustomTable->QcDocument->find('first', array('recursive' => 0, 'conditions' => array('QcDocument.id' => $qc_doc_id),));
            $tableName = $qcDocument['QcDocument']['title'] . '_' . $qcDocument['QcDocument']['revision_number'];
            $tableName = $this->_clean_table_names($tableName);
            $tableName = $tableName . '_v';
            // check if any other tables is already created.
            $version = $this->CustomTable->find('count', array('conditions' => array('CustomTable.table_name LIKE' => '%' . $tableName . '%')));
            if($version)$version = $version + 1;
            else $version = 1;
            $tableName = $qcDocument['QcDocument']['title'] . '_' . $qcDocument['QcDocument']['revision_number'];
            $tableName = ltrim(rtrim($tableName));
            $tableName = 'tbl_'.$this->_clean_table_names($tableName);
            $tableName = Inflector::pluralize($tableName . '_v' . $version);
            return array($tableName, $version);
        }else if($type == 'processes'){
            $process = $this->CustomTable->Process->find('first', array('recursive' => 0, 'conditions' => array('Process.id' => $qc_doc_id),));
            $tableName = $process['Process']['name'];
            $tableName = ltrim(rtrim($tableName));
            $tableName = $this->_clean_table_names($tableName);
            $tableName = $tableName . '_v';
            // check if any other tables is already created.
            $version = $this->CustomTable->find('count', array('conditions' => array('CustomTable.table_name LIKE' => '%' . $tableName . '%')));
            if($version)$version = $version + 1;
            else $version = 1;
            $tableName = $tableName = $process['Process']['name'].'_'. $version;
            $tableName = 'tbl_'.$this->_clean_table_names($tableName);
            $tableName = Inflector::pluralize($tableName . '_v' . $version);
            return array($tableName, $version);
        }else if($type == 'masters'){            
            $tableName = $this->request->data['CustomTable']['name'];
            $tableName = ltrim(rtrim($tableName));
            $tableName = $this->_clean_table_names($tableName);
            $tableName = $tableName . '_v';
            // check if any other tables is already created.
            $version = $this->CustomTable->find('count', array('conditions' => array('CustomTable.table_name LIKE' => '%' . $tableName . '%')));
            $version = 1;
            $tableName = 'tbl_'.$this->_clean_table_names($tableName);
            $tableName = Inflector::pluralize($tableName .$version);
            return array($tableName, $version);
        }        
    }
    
    public function _make_child_table_name($qc_doc_id = null, $type = null, $linked = null) {
        if($type == 'qc_documents'){
            $qcDocument = $this->CustomTable->QcDocument->find('first', array('recursive' => 0, 'conditions' => array('QcDocument.id' => $qc_doc_id),));
            $linked = $linked + 1;
            $tableName = $qcDocument['QcDocument']['title'] . '' . $qcDocument['QcDocument']['revision_number'];
            $tableName = $this->_clean_table_names(ltrim(rtrim($tableName)));
            $tableName = $tableName . '_child_' . $linked . '_v';
            // check if any other tables is already created.
            $version = $this->CustomTable->find('count', array('conditions' => array('CustomTable.table_name LIKE' => '%' . $tableName . '%')));
            $version = $version + 1;
            $tableName = $qcDocument['QcDocument']['title'] . '' . $qcDocument['QcDocument']['revision_number'];
            $tableName = 'chd_'.$this->_clean_table_names($tableName);
            $tableName = Inflector::pluralize($tableName . '_child_' . $linked . '_v' . $version);
        }else if($type == 'processes'){
            $process = $this->CustomTable->Process->find('first', array('recursive' => 0, 'conditions' => array('Process.id' => $qc_doc_id),));
            $tableName = 'chd_'.$this->_clean_table_names(trim($process['Process']['name']));
            $tableName = $tableName;            
            $version = $this->CustomTable->find('count', array('conditions' => array('CustomTable.custom_table_id !='=>'', 'CustomTable.table_name LIKE' => '%' . $tableName .'%' )));
            $version = $version + 1;
            $tableName = 'chd_'.$this->_clean_table_names($process['Process']['name'].'_'. $version);
            $tableName = Inflector::pluralize($tableName . '_child_' . $linked . '_v' . $version);
        }
        return array($tableName, $version);
    }
    
    public function add_fields($type = null, $id = null, $f = null) {
        
    }

    public function existing_fields($type = null, $id = null, $f = null,$field = null,$custom_table_id = null) {
        $this->set('f', $f);
        $this->set('id', $id);
        $this->set('type', $type);
        $field_name = base64_decode($field);        
        $table = $this->CustomTable->find('first',array('conditions'=>array('CustomTable.id'=>$custom_table_id),'recursive'=>-1,'fields'=>array('CustomTable.id','CustomTable.fields')));
        $fields = json_decode($table['CustomTable']['fields'],true);
        foreach($fields as $field){
            if($field['field_name'] == $field_name){
                $fieldDetails = $field;
            }
        }
        $this->set('fieldDetails', $fieldDetails);
        $this->_commons($this->Session->read('User.id'));
    }

    // 1st step add new table
    // Owner's fifth review (16 Nov 2026), decision 392: the table work is collected here and applied at the end of the submit by
    // _m9_tx_commit (CREATE if the table is missing, then the column changes as one ALTER). Outside a submit it does nothing.
    public function _add_new_table($table_name = null,$defaultfield = null,$sqld = null,$fields = null){
        if (!is_array($this->m9Tx)) { $this->_m9_ac_log('builder_ddl_outside_submit', substr(preg_replace('/[^A-Za-z0-9_]/', '', (string)$table_name), 0, 64)); throw new M9BuilderAbort('ddl_outside_submit'); }
        $alter = isset($this->request->data['CustomTable']['table_name']) ? (string)$this->request->data['CustomTable']['table_name'] : '';
        foreach (array((string)$table_name, $alter) as $tn) if (preg_match('/^(tbl|chd)_[a-z0-9_]{1,60}$/', $tn) !== 1) throw new M9BuilderAbort('bad_table_name');
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', (string)$defaultfield) !== 1) throw new M9BuilderAbort('bad_default_field');
        $sqld = (string)$sqld;
        if ($sqld !== '' && preg_match("/^(\\s*`(qc_document_id|process_id|custom_table_id)` varchar\\(36\\) (NOT NULL DEFAULT '[0-9a-f-]{36}'|NULL'?),)+\\s*$/i", $sqld) !== 1) throw new M9BuilderAbort('bad_table_definition');
        $list = array();
        foreach ((array)$fields as $f) if (is_array($f)) $list[] = $f;
        $this->m9Tx['ddl'][] = array('create' => (string)$table_name, 'default' => (string)$defaultfield, 'sqld' => $sqld, 'alter' => $alter, 'fields' => $list);
        return true;
    }
    /**
     * add method
     *
     * @return void
     */
    public function add() {
        if($this->Session->read('User.is_mr') == false){
            $this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
            $this->redirect(array('controller' => 'users', 'action' => 'access_denied',$n));
        }

        if(empty($this->request->params['named']['qc_document_id']) && empty($this->request->params['named']['process_id'])){
            $this->Session->setFlash(__('Select Document/Process first'), 'default', array('class' => 'alert alert-danger'));
            $this->redirect(array('controller' => 'custom_tables', 'action' => 'index',$n));
        }

        if ($this->_show_approvals()) {
            $this->loadModel('User');
            $this->User->recursive = 0;
            $userids = $this->User->find('list', array('order' => array('User.name' => 'ASC'), 'conditions' => array('User.publish' => 1, 'User.soft_delete' => 0, 'User.is_approver' => 1)));
            $this->set(array('userids' => $userids));
            $this->set(array('showApprovals' => $this->_show_approvals()));
        }
        // block adding table if document is not available
        if($this->request->params['named']['qc_document_id']){
            $options = array('recursive' => - 1, 'conditions' => array('QcDocument.id' => $this->request->params['named']['qc_document_id']));
            $qcDoc = $this->CustomTable->QcDocument->find('first', $options);
            $this->set('qcDoc', $qcDoc);
            $file_type = $qcDoc['QcDocument']['file_type'];
            $file_name = $qcDoc['QcDocument']['title'];
            $document_number = $qcDoc['QcDocument']['document_number'];
            $document_version = $qcDoc['QcDocument']['revision_number'];
            $file_name = $document_number . '-' . $file_name . '-' . $document_version;
            $file_name = $this->_clean_table_names($file_name);
            $file_name = $file_name . '.' . $file_type;

            $qcpfile = WWW_ROOT .  'files' . DS . $this->Session->read('User.company_id') . DS . 'qc_documents' . DS . $this->request->params['named']['qc_document_id'] . DS . $file_name;
            
            if (!file_exists($qcpfile)) {
                $this->Session->setFlash(__('You are creating HTML form without any document.'));                
            }    
        }else{
            $this->Session->setFlash(__('You are creating HTML form without any document.'));            
        }        

        $this->_commons($this->Session->read('User.id'));
        
        // after form submit
        if ($this->request->is('post')) {
            if (false === $this->_m9_generator_ready()) $this->_m9_generator_refuse(); // owner's fourth review: no change unless the generator answers
            $this->_m9_tx_begin(); // owner's fifth review (decision 392): from here this submit changes everything or nothing
            
            $this->_commons($this->Session->read('User.id'));
            
            $this->request->data['CustomTable']['password'] = Security::hash($this->request->data['CustomTable']['password'], 'md5', true);
            
            foreach($this->request->data['CustomTableFields'] as $fields){
                if($fields['default_field'] == 1){
                    $defaultfield = $this->_clean_table_names($fields['field_name']);
                }
            }

            if ($defaultfield == '') {
                $this->Session->setFlash(__('Default field missing'));
                $this->redirect(array('action' => 'add', $id, 'qc_document_id' => $this->request->params['named']['qc_document_id']));
            }

            $fieldTypes = $this->CustomTable->customArray['fieldTypes'];
            
            if(isset($this->request->params['named']['qc_document_id'])){
                $table_name_version = $this->_make_table_name($this->request->data['CustomTable']['qc_document_id'],'qc_documents');

            // for processes                
            }else if(isset($this->request->params['named']['process_id'])){                
                $table_name_version = $this->_make_table_name($this->request->data['CustomTable']['process_id'],'processes');
            // if none
            }else{
                
            }

            $this->request->data['CustomTable']['table_name'] = $table_name_version[0];
            $this->request->data['CustomTable']['table_version'] = $table_name_version[1];
            $table_name = $table_name_version[0];
            //prepare sql
            $this->request->data['CustomTable']['fields'] = json_encode($this->request->data['CustomTableFields']);
            $this->request->data['CustomTable']['system_table_id'] = $this->_get_system_table_id();

            $this->CustomTable->create();
            // move new onlyoffice document to new location
            if($this->request->data['CustomTable']['qc_document_id']){
                $options = array('recursive' => - 1, 'conditions' => array('QcDocument.id' => $this->request->data['CustomTable']['qc_document_id']));
                $qcDoc = $this->CustomTable->QcDocument->find('first', $options);
                $this->set('qcDoc', $qcDoc);
                $file_type = $qcDoc['QcDocument']['file_type'];
                $file_name = $qcDoc['QcDocument']['title'];
                $document_number = $qcDoc['QcDocument']['document_number'];
                $document_version = $qcDoc['QcDocument']['revision_number'];
                // $file_name = $document_number . '-' . $file_name . '-' . $document_version . '.' . $file_type;

                $file_name = $document_number . '-' . $file_name . '-' . $document_version;
                $file_name = $this->_clean_table_names($file_name);
                $file_name = $file_name . '.' . $file_type;

                $qcpfile = WWW_ROOT .  'files' . DS . $this->Session->read('User.company_id') . DS . 'custom_tables' . DS . $this->request->data['CustomTable']['qc_document_id'] . DS . $file_name;
                if (!file_exists($qcpfile)) {
                    
                }    
            }else if($this->request->data['CustomTable']['process_id']){
                $options = array('recursive' => - 1, 'conditions' => array('Process.id' => $this->request->data['CustomTable']['process_id']));
                $proDoc = $this->CustomTable->Process->find('first', $options);
                $this->set('proDoc', $proDoc);
                $file_type = $proDoc['Process']['file_type'];
                $file_name = $proDoc['Process']['file_name'];
                
                $file_name = $file_name;
                $qcpfile = WWW_ROOT .  'files' . DS . $this->Session->read('User.company_id') . DS . 'custom_tables' . DS . $this->request->data['CustomTable']['process_id'] . DS . $file_name;
                if (!file_exists($qcpfile)) {                    
                    
                }
            }
            
            $newFields = array();
            foreach($this->request->data['CustomTableFields'] as $fields){
                if($fields['drop'] == 1){
                    unset($fields);
                }else{
                    $fields['who_can_edit'] = json_encode($fields['who_can_edit']);
                    if($fields['display_type'] == 7)$fields['show_comments'] = base64_encode($fields['show_comments']);
                    // $fields['field_label'] = Inflector::humanize($this->_clean_table_names($fields['field_label']));
                    $fields['field_label'] = base64_encode($fields['field_label']);
                    $newFields[] = $fields;        
                }                
            }
            
            if(is_array($newFields)){
                $this->request->data['CustomTableFields'] = $newFields;
                $this->request->data['CustomTable']['fields'] = json_encode($newFields);    
            }

            if ($this->CustomTable->save($this->request->data)) {
                $qcDocument = $this->CustomTable->QcDocument->find('first',array('conditions'=>array('QcDocument.id'=>$this->request->data['CustomTable']['qc_document_id']),'recursive'=>-1,));
                if($qcDocument){
                    $qcDocument['QcDocument']['schedule_id'] = $this->request->data['QcDocument']['schedule_id'];
                    $qcDocument['QcDocument']['data_type'] = $this->request->data['QcDocument']['data_type'];
                    $qcDocument['QcDocument']['data_update_type'] = $this->request->data['QcDocument']['data_update_type'];
                    $this->CustomTable->QcDocument->save($qcDocument,false);
                }

                // update qc document schedule & types
                
                $file = Configure::read('path') . DS . $this->CustomTable->id . DS . $file_name;
                
                if (!file_exists(Configure::read('path') . DS . $this->CustomTable->id)) {
                    $pathfolder = new Folder();
                    // create folder
                    if ($pathfolder->create(Configure::read('path') . DS . $this->CustomTable->id)) {
                        $this->_m9_mode(Configure::read('path') . DS . $this->CustomTable->id);
                        $this->_m9_tx_note_created(Configure::read('path') . DS . $this->CustomTable->id);
                        
                        if(file_exists($qcpfile)){
                            if (($m9c = $this->_m9_safe_copy(Configure::read('path'), $this->CustomTable->id, basename($file), $qcpfile)) && $m9c[0] && $this->_m9_tx_note_created($m9c[1])) {
                                $key = $this->_generate_onlyoffice_key($this->CustomTable->id . date('Ymdhis'));
                                $this->set('file_key', $key);

                            } else {
                                $this->Session->setFlash(__('Copy file failed'));
                                $this->redirect(array('action' => 'index', 'qc_document_id' => $this->request->params['named']['qc_document_id'],'process_id' => $this->request->params['named']['process_id']));
                            }    
                        }

                        $rec = $this->CustomTable->find('first', array('recursive' => - 1, 'conditions' => array('CustomTable.id' => $this->CustomTable->id)));
                        $rec['CustomTable']['file_key'] = $key;
                        $this->CustomTable->create();
                        $this->CustomTable->save($rec['CustomTable']);
                        
                        if($this->request->data['CustomTable']['qc_document_id']){
                            $sqld.= "`qc_document_id` varchar(36) NOT NULL DEFAULT '" . $this->request->data['CustomTable']['qc_document_id'] . "',
                            ";
                        }else if($this->request->data['CustomTable']['process_id']){
                            $sqld.= "`process_id` varchar(36) NOT NULL DEFAULT '" . $this->request->data['CustomTable']['process_id'] . "',
                            ";
                            $sqld.= "`qc_document_id` varchar(36) NULL',
                            ";
                        }
                        
                        $sqld .= "`custom_table_id` varchar(36) NOT NULL DEFAULT '" . $this->CustomTable->id . "',
                        ";
                        
                        $friendlyName = $this->request->data['CustomTable']['name'];
                        
                        unset($this->request->data['History']);
                        $data = array($this->request->data,$table_name,$friendlyName,$defaultfield);

                        $result = $this->curl('post','custom_forms', 'create',$data);
                        $result = json_decode($result,true);
                        $this->_m9_tx_check_answer($result, 'create'); // decision 392: the whole answer is checked before it is used
                        
                        if($result['error'] == 1){
                            echo "Something went wrong. Please try again";
                        }else{                            
                            $result = json_decode($result['response']['finalResult'],true);
                            if($result['controller']){
                                $controller_file_name = Inflector::pluralize(Inflector::Classify($table_name)) . 'Controller.php';
                                $folder = APP . 'Controller';
                                $file = $folder .  DS . $controller_file_name;
                                $this->_write_to_file($folder,$file,$result['controller']);
                            }

                            if($result['model']){

                                $name = Inflector::Classify($table_name) . '.php';
                                $folder = APP . 'Model';
                                $file = $folder . DS . $name;
                                $this->_write_to_file($folder,$file,$result['model']);
                            }                            

                            $viewCode = json_decode($result['viewFile'],true);

                            $folder = APP . 'View' . DS . Inflector::pluralize(Inflector::classify($table_name));        
                            $modelFolder = new Folder();
                            $this->_m9_mode($folder);

                            if($viewCode['index']){
                                $file = $folder . DS . 'index.ctp';
                                $this->_write_to_file($folder,$file,$viewCode['index']);
                            }

                            if($viewCode['index']){
                                $file = $folder . DS . 'view.ctp';                                
                                $this->_write_to_file($folder,$file,$viewCode['view']);
                                
                            }

                            $addCode = json_decode($result['formFile'],true);

                            if($addCode['add']){
                                $file = $folder . DS . 'add.ctp';                                
                                $this->_write_to_file($folder,$file,$addCode['add']);
                                
                            }

                            if($addCode['edit']){
                                $file = $folder . DS . 'edit.ctp';                                
                                $this->_write_to_file($folder,$file,$addCode['edit']);
                                
                            }
                        }

                        $sqlresult = $this->_add_new_table($table_name,$defaultfield,$sqld,$this->request->data['CustomTableFields']);
                        $this->_m9_approval_begin();
                        if ($this->_show_approvals() && !$this->_save_approvals($this->CustomTable->id)) throw new M9BuilderAbort('approval_save_failed');
                        $this->_m9_tx_commit(); // decision 392: files, then the table; on failure all is undone
                        $this->_m9_approval_commit(); // mail leaves only after the form commit succeeded
                        // $this->_clear_cake_cache();
                        // everything run properly -- now create table
                        $this->Session->setFlash(__('Form created'));
                        $this->redirect(array('action' => 'recreate',$this->CustomTable->id, 'qc_document_id' => $this->request->params['named']['qc_document_id'],'process_id' => $this->request->params['named']['process_id']));
                        
                    } else {
                        echo "failed folder - 1" . Configure::read('path');
                        $this->Session->setFlash(__('Custom Table id folder could not be created'));
                        $this->redirect(array('action' => 'index', 'qc_document_id' => $this->request->params['named']['qc_document_id'],'process_id' => $this->request->params['named']['process_id']));
                    }
                } else {
                    $this->Session->setFlash(__('Custom Table id folder already exixts'));
                    $this->redirect(array('action' => 'index', 'qc_document_id' => $this->request->params['named']['qc_document_id'],'process_id' => $this->request->params['named']['process_id']));
                }
                $this->redirect(array('action' => 'index', 'qc_document_id' => $this->request->params['named']['qc_document_id'],'process_id' => $this->request->params['named']['process_id']));
            } else {
                $this->Session->setFlash(__('The custom table could not be saved. Please, try again.'));
            }
        }
        if ($this->request->params['named']['qc_document_id'] || $this->request->params['named']['process_id']) {
            // generate table name
            // for documets
            if(isset($this->request->params['named']['qc_document_id'])){
                $qcDocument = $this->CustomTable->QcDocument->find('first', array('recursive' => 0, 'conditions' => array('QcDocument.id' => $this->request->params['named']['qc_document_id']),));
                $table_name_version = $this->_make_table_name($this->request->params['named']['qc_document_id'],'qc_documents');
                $this->set('qcDocument', $qcDocument);
                $this->set('name', $qcDocument['QcDocument']['name']);
                $this->set('table_name', $table_name_version[0]);
                $this->set('table_version', $table_name_version[1]);
                $this->_set_file($this->request->params['named']['qc_document_id'],'qc_documents');

            // for processes                
            }else if(isset($this->request->params['named']['process_id'])){
                $process = $this->CustomTable->Process->find('first', array('recursive' => 0, 'conditions' => array('Process.id' => $this->request->params['named']['process_id']),));
                $table_name_version = $this->_make_table_name($this->request->params['named']['process_id'],'processes');
                $this->set('process', $process);
                $this->set('table_name', $table_name_version[0]);
                $this->set('table_version', $table_name_version[1]);
                $this->_set_file($this->request->params['named']['process_id'],'processes');
            // if none
            }else{
                
            }
            
            if($qcDocument['QcDocument']['parent_document_id']){
                $parent = $this->CustomTable->find('first',array(
                    'recursive'=>-1,
                    'fields'=>array('CustomTable.id','CustomTable.fields'),
                    'conditions'=>array('CustomTable.qc_document_id'=>$qcDocument['QcDocument']['parent_document_id'])));
                
                if($parent){
                    $parentFields = json_decode($parent['CustomTable']['fields'],true);                    
                    foreach ($parentFields as $f) {                        
                        if($f["data_type"] == 'radio')$fieldNames[$f["field_name"]] = $f["field_name"];
                        $fields[$f["field_name"]] = $f;
                        $this->set("fieldNames", $fieldNames);
                        $this->set("fields", $fields);   
                        $this->set("parent",$parent);                     
                    }
                }

            }

            if(!$qcDocument && !$process){
                $this->Session->setFlash(__('Select Document/Process first'), 'default', array('class' => 'alert alert-warning'));
                $this->redirect(array('controller' => 'custom_tables', 'action' => 'index',$n));   
            }
            
        } else {
            $this->Session->setFlash(__('Chose document first to create table. Tables are linked with documents and document must be present before adding table.'));
            $this->redirect(array('controller' => 'qc_documents', 'action' => 'index'));
        }        
    }

    // Owner's fourth review (13 Nov 2026): restores a form's copy of its document, the copy custom_tables/view used to make on a
    // GET. POST only (the session token is checked for every POST), administrator (list in AppController), the form's creator
    // (the rule of the view page). The source must be the form's own document file inside files/<company>/qc_documents/<document>/,
    // the target inside files/<company>/custom_tables/<form>/, both after resolving links and '..'; an existing copy is never
    // overwritten. Every outcome is logged.
    public function restore_form_document($id = null) {
        $k = 'custom_tables/restore_form_document';
        if (false === $this->request->is('post')) $this->_m9_ac_refuse('post_required', $k);
        if ($this->Session->read('User.is_mr') == false) $this->_m9_ac_refuse('admin_required', $k);
        if (!is_string($id) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) $this->_m9_ac_refuse('bad_id', $k);
        $customTable = $this->CustomTable->find('first', array('recursive' => -1, 'conditions' => array('CustomTable.id' => $id)));
        if (!$customTable) $this->_m9_ac_refuse('form_not_found', $k);
        $id = (string)$customTable['CustomTable']['id']; // the stored id, not the text of the address
        if ($customTable['CustomTable']['created_by'] != $this->Session->read('User.id')) $this->_m9_ac_refuse('not_form_creator', $k);
        $back = array('action' => 'view', $id);
        $qcDocument = $this->_qc_document_header($customTable['CustomTable']['qc_document_id']);
        if (empty($qcDocument['QcDocument']['id'])) { $this->_m9_ac_log('form_document_not_restored', $id . ' no_document'); $this->Session->setFlash(__('This form has no document.')); $this->redirect($back); }
        $file_name = $this->_clean_table_names($qcDocument['QcDocument']['document_number'] . '-' . $qcDocument['QcDocument']['title'] . '-' . $qcDocument['QcDocument']['revision_number']) . '.' . $qcDocument['QcDocument']['file_type'];
        $base = realpath(WWW_ROOT . 'files' . DS . $this->Session->read('User.company_id'));
        $src = ($base === false) ? false : realpath($base . DS . 'qc_documents' . DS . $qcDocument['QcDocument']['id'] . DS . $file_name);
        if ($src === false || !is_file($src) || strpos($src, $base . DS . 'qc_documents' . DS . $qcDocument['QcDocument']['id'] . DS) !== 0) {
            $this->_m9_ac_log('form_document_not_restored', $id . ' source_missing'); $this->Session->setFlash(__('The document file was not found; nothing was changed.')); $this->redirect($back);
        }
        // owner's fifth review (decision 393): the copy goes through _m9_safe_copy, which never writes through a symbolic link
        $m9c = $this->_m9_safe_copy($base, 'custom_tables/' . $id, basename($src), $src, false);
        if (!$m9c[0] && $m9c[1] === 'already_present') { $this->_m9_ac_log('form_document_not_restored', $id . ' already_present'); $this->Session->setFlash(__('The form document is already present; nothing was changed.')); $this->redirect($back); }
        if (!$m9c[0]) { $this->_m9_ac_log('form_document_not_restored', $id . ' ' . $m9c[1]); $this->Session->setFlash(__('The form document could not be restored; nothing was copied.')); $this->redirect($back); }
        $dst = $m9c[1];
        $this->_m9_ac_log('form_document_restored', $id . ' ' . basename($dst));
        $this->Session->setFlash(__('The form document was restored from the document.'));
        $this->redirect($back);
    }

    // ===== Owner's fifth review (16 Nov 2026), decision 392: a form-builder submit changes everything or nothing =====
    // add, add_child, recreate, recreate_child and create_master save the form definition, ask the vendor's generator for the
    // form's code, write that code into app/Controller, app/Model and app/View, and create or alter the form's table. Asking the
    // generator first (fourth review) did not make the rest safe: a failure later in the submit still left part of it done.
    // From the start of a submit (_m9_tx_begin):
    //  - every row the submit writes to custom_tables, approval_processes or qc_documents is journalled by AppModel (decision 397,
    //    owner's sixth review): the row before this submit first wrote it and the row as this submit left it; nothing else is kept;
    //  - generated files and table changes are only collected, not applied (_write_to_file, _add_new_table, create_master);
    //  - the generator's answer is checked in full before it is used: every expected part present, every generated PHP file parses;
    //  - only at the end of a successful submit (_m9_tx_commit): each file is written beside its target and read back, the files
    //    it replaces are kept under tmp/m9_builder/<id>/, the new files are renamed into place, and then the table is created if
    //    missing and changed by ONE ALTER statement (all column changes apply or none), and its columns are read back and compared;
    //  - on any failure (generator error, incomplete answer, a refused or failed step, a redirect or exit before the end, a fatal
    //    error), everything the submit did is undone (_m9_tx_rollback): only the journalled rows, each only while it is still as
    //    this submit left it (a row another user changed meanwhile is kept as they made it and reported), files put back, folders
    //    it made removed, a table it created dropped while still empty.
    // Outcomes are logged to tmp/logs/m9_access.log: builder_committed, builder_rolled_back, builder_rollback_incomplete.
    // Generated files may only be form files (Tbl*/Chd* controllers, models and view folders); generated tables only tbl_/chd_.
    public $m9Tx = null;
    public $m9TxLock = '';
    public $m9TxShutdown = false;

    public function invokeAction(CakeRequest $request) {
        // recreate and recreate_child take their skip/data arguments only from recreate_all_forms, never from the address
        if (isset($request->params['action']) && in_array($request->params['action'], array('recreate', 'recreate_child'), true) && isset($request->params['pass'])) {
            $request->params['pass'] = array_slice((array)$request->params['pass'], 0, 1);
        }
        try {
            $r = parent::invokeAction($request);
        } catch (M9BuilderAbort $e) {
            $this->_m9_tx_rollback($e->getMessage(), true);
            if ($this->request->is('ajax')) { $this->response->statusCode(409); $this->response->body(__('The form was not changed.')); $this->response->send(); $this->_stop(); }
            return $this->redirect(array('action' => 'index'));
        } catch (Throwable $e) {
            $this->_m9_tx_rollback('error ' . get_class($e), true);
            throw $e;
        }
        if (is_array($this->m9Tx)) $this->_m9_tx_rollback('ended_before_commit');
        return $r;
    }

    public function redirect($url, $status = null, $exit = true) {
        if (is_array($this->m9Tx)) $this->_m9_tx_rollback('ended_before_commit');
        return parent::redirect($url, $status, $exit);
    }

    public function _m9_tx_shutdown() {
        if (is_array($this->m9Tx)) { try { $this->_m9_tx_rollback('request_ended'); } catch (Throwable $e) { } }
        elseif ($this->m9TxLock !== '') { try { $this->_m9_tx_release_builder(); } catch (Throwable $e) { } }
    }

    protected function _m9_tx_pdo() {
        return ConnectionManager::getDataSource('default')->getConnection();
    }

    protected function _m9_tx_name($m) {
        $t = (string)$m->tablePrefix . (string)$m->table;
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $t) !== 1) throw new M9BuilderAbort('bad_table_name');
        return $t;
    }

    // M9 full fix: serialise every builder submit on this database. Form builder writes the same
    // generated files and the same definition rows; a second submit must wait (or be refused) instead
    // of interleaving its journal, file backups and DDL with the first one.
    protected function _m9_lock_acquire($name, $timeout = 10) {
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', (string)$name) !== 1) return false;
        try {
            $q = $this->_m9_tx_pdo()->prepare('SELECT GET_LOCK(?, ?)');
            $q->execute(array((string)$name, max(1, (int)$timeout)));
            return ((string)$q->fetchColumn() === '1');
        } catch (Throwable $e) { return false; }
    }

    protected function _m9_lock_release($name) {
        if (!is_string($name) || $name === '') return;
        try {
            $q = $this->_m9_tx_pdo()->prepare('SELECT RELEASE_LOCK(?)');
            $q->execute(array($name));
        } catch (Throwable $e) { }
    }

    protected function _m9_tx_unlock_files($tx) {
        if (!is_array($tx) || empty($tx['fileLocks'])) return;
        foreach ($tx['fileLocks'] as $lock) $this->_m9_file_unlock($lock);
    }

    protected function _m9_tx_release_builder() {
        $this->_m9_lock_release($this->m9TxLock);
        $this->m9TxLock = '';
    }

    public function _m9_tx_begin() {
        if (is_array($this->m9Tx)) throw new M9BuilderAbort('nested_submit');
        if ($this->m9TxLock !== '') throw new M9BuilderAbort('nested_submit');
        $lock = 'm9_builder';
        if (false === $this->_m9_lock_acquire($lock, 10)) throw new M9BuilderAbort('builder_busy');
        $this->m9TxLock = $lock;
        try {
            $this->loadModel('ApprovalProcess');
            $this->loadModel('Approval');
            // decision 397: from here AppModel journals every row this request writes to these tables; nothing is read in advance
            AppModel::m9_journal_start(array($this->_m9_tx_name($this->CustomTable), $this->_m9_tx_name($this->ApprovalProcess), $this->_m9_tx_name($this->Approval), $this->_m9_tx_name($this->CustomTable->QcDocument)));
            $this->m9Tx = array('id' => date('Ymd_His') . '_' . bin2hex(random_bytes(3)), 'action' => (string)$this->action,
                'files' => array(), 'ddl' => array(), 'made' => array(), 'noted' => array(), 'stage' => array(), 'created' => array(),
                'fileLocks' => array(), 'shadow' => array(), 'answer' => '', 'done' => false, 'user' => (string)$this->Session->read('User.id'));
        } catch (Throwable $e) {
            $this->_m9_lock_release($lock);
            $this->m9TxLock = '';
            throw $e;
        }
        if (false === $this->m9TxShutdown) { $this->m9TxShutdown = true; register_shutdown_function(array($this, '_m9_tx_shutdown')); }
        return true;
    }

    // A file or folder the submit itself made outside the form folders (the form's document copy in add): removed on undo.
    public function _m9_tx_note_created($path) {
        if (is_array($this->m9Tx)) $this->m9Tx['noted'][] = (string)$path;
        return true;
    }

    // Called by AppController::_write_to_file during a submit.
    public function _m9_tx_queue_file($file, $content) {
        if (!is_array($this->m9Tx)) throw new M9BuilderAbort('no_transaction');
        $file = (string)$file; $content = (string)$content; $app = rtrim(APP, DS);
        $rel = (strpos($file, $app . DS) === 0) ? str_replace(DS, '/', substr($file, strlen($app) + 1)) : '';
        if (preg_match('#^(Controller/(Tbl|Chd)[A-Za-z0-9]+Controller\.php|Model/(Tbl|Chd)[A-Za-z0-9]+\.php|View/(Tbl|Chd)[A-Za-z0-9]+/[A-Za-z0-9_]+\.ctp)$#', $rel) !== 1) {
            throw new M9BuilderAbort('generated_file_not_a_form_file ' . substr(preg_replace('/[^A-Za-z0-9_.\/-]/', '', $rel), 0, 80));
        }
        try { token_get_all($content, TOKEN_PARSE); } catch (ParseError $e) { throw new M9BuilderAbort('generated_code_does_not_parse ' . $rel . ' line ' . $e->getLine()); }
        $this->m9Tx['files'][$rel] = $content;
        return true;
    }

    // The generator's answer, checked in full before the vendor code reads it.
    public function _m9_tx_check_answer($r, $kind) {
        if (!is_array($this->m9Tx)) throw new M9BuilderAbort('no_transaction');
        if (!is_array($r) || (isset($r['error']) && $r['error'] == 1) || !isset($r['response']['finalResult']) || !is_string($r['response']['finalResult'])) throw new M9BuilderAbort('generator_error');
        $f = json_decode($r['response']['finalResult'], true);
        if (!is_array($f)) throw new M9BuilderAbort('generator_answer_unreadable');
        $j = function ($k) use ($f) { if (!isset($f[$k]) || !is_string($f[$k])) return null; $d = json_decode($f[$k], true); return is_array($d) ? $d : null; };
        $s = function ($a, $k) { return is_array($a) && isset($a[$k]) && is_string($a[$k]) && trim($a[$k]) !== ''; };
        $ok = false;
        if ($kind === 'create') {
            $v = $j('viewFile'); $a = $j('formFile');
            $ok = $s($f, 'controller') && $s($f, 'model') && $s($v, 'index') && $s($v, 'view') && $s($a, 'add') && $s($a, 'edit');
        } elseif ($kind === 'create_child') {
            $a = $j('formFile');
            $ok = $s($f, 'controller') && $s($f, 'model') && $s($f, 'index') && is_array($a) && count($a) > 0;
            if ($ok) foreach ($a as $k => $c) if (!is_string($k) || preg_match('/^[A-Za-z0-9_]+$/', $k) !== 1 || !is_string($c) || trim($c) === '') $ok = false;
        } elseif ($kind === 'create_masters') {
            $c = $j('controller_model'); $v = $j('viewFile'); $a = $j('formFile');
            $ok = $s($c, 'controller') && $s($c, 'model') && $s($v, 'index') && $s($v, 'view') && $s($a, 'add') && $s($a, 'edit');
        }
        if (!$ok) throw new M9BuilderAbort('generator_answer_incomplete');
        $this->m9Tx['answer'] = $kind;
        return true;
    }

    // FlinkISO's CREATE statement for a form table (unchanged text, from _add_new_table).
    protected function _m9_tx_create_sql($table_name, $defaultfield, $sqld) {
        $sql = "CREATE TABLE IF NOT EXISTS `" . $table_name . "` ( ";
        $sql .= "`id` varchar(36) NOT NULL ,";
        $sql .= "`sr_no` int(11) NOT NULL AUTO_INCREMENT,";
        $sql .= "`".$defaultfield."` varchar(255) NOT NULL,";
        $sql .= $sqld;
        $sql .= "
        `file_id` varchar(36) NULL,
        `file_key` varchar(50) NULL,
        `parent_id` varchar(36) NULL,
        `additional_files` text NULL,
        `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
        `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
        `status_user_id` varchar(36) DEFAULT NULL,
        `approval_step_id` varchar(36) DEFAULT NULL,
        `created_by` varchar(36) NOT NULL,
        `created` datetime NOT NULL,
        `modified_by` varchar(36) NOT NULL,
        `approved_by` varchar(36) DEFAULT NULL,
        `prepared_by` varchar(36) DEFAULT NULL,
        `modified` datetime NOT NULL,
        `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
        `branchid` varchar(36) DEFAULT NULL,
        `departmentid` varchar(36) DEFAULT NULL,
        `company_id` varchar(36) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `sr_no` (`sr_no`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
        ";
        return $sql;
    }

    public function _m9_tx_queue_create($table, $sql) {
        if (!is_array($this->m9Tx)) throw new M9BuilderAbort('no_transaction');
        if (preg_match('/^(tbl|chd)_[a-z0-9_]{1,60}$/', (string)$table) !== 1) throw new M9BuilderAbort('bad_table_name');
        $this->m9Tx['ddl'][] = array('create' => (string)$table, 'raw_create' => (string)$sql, 'alter' => '', 'fields' => array());
        return true;
    }

    protected function _m9_tx_table_exists($t) {
        $q = $this->_m9_tx_pdo()->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $q->execute(array($t));
        return (int)$q->fetchColumn() > 0;
    }

    protected function _m9_tx_columns($t) {
        if (!$this->_m9_tx_table_exists($t)) throw new M9BuilderAbort('table_missing ' . $t);
        $out = array();
        foreach ($this->_m9_tx_pdo()->query('SHOW COLUMNS FROM `' . $t . '`')->fetchAll(PDO::FETCH_ASSOC) as $c) $out[$c['Field']] = true;
        return $out;
    }

    protected function _m9_tx_ddl_run($sql, $what) {
        AppModel::$m9Ddl++;
        try { $r = $this->CustomTable->query($sql); } catch (Exception $e) { $r = false; } finally { AppModel::$m9Ddl--; }
        if ($r === false) throw new M9BuilderAbort('table_statement_failed ' . $what);
        return true;
    }

    protected function _m9_tx_type($f) {
        $t = isset($f['field_type']) ? (string)$f['field_type'] : ''; $len = isset($f['length']) ? (string)$f['length'] : '';
        $sized = array('0' => 'varchar', '2' => 'int', '3' => 'tinyint', '4' => 'float', '7' => 'varchar');
        if (isset($sized[$t])) {
            if (preg_match('/^[1-9][0-9]{0,4}$/', $len) !== 1) throw new M9BuilderAbort('bad_field_length');
            return $sized[$t] . '(' . $len . ')';
        }
        if ($t === '1') return 'text';
        if ($t === '5') return 'date';
        if ($t === '6') return 'datetime';
        return 'text';
    }

    protected function _m9_tx_ident($n) {
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', (string)$n) !== 1) throw new M9BuilderAbort('bad_field_name');
        return (string)$n;
    }

    // The column changes FlinkISO's _add_new_table asked for, as one list, against the table as it is. FlinkISO issued one
    // statement per change and ignored the ones the database refused (dropping a column that is not there, adding one that is);
    // those are not issued here, so the one ALTER does exactly what FlinkISO's statements that succeeded did.
    protected function _m9_tx_plan($cols, $lists) {
        $clauses = array(); $expect = array();
        foreach ($lists as $fields) foreach ($fields as $f) {
            $name = isset($f['field_name']) ? (string)$f['field_name'] : '';
            if ($name === '' || in_array($name, $this->reserved_fields, true)) continue;
            $old = isset($f['old_field_name']) ? (string)$f['old_field_name'] : '';
            $drop = isset($f['drop']) && $f['drop'] == 1; $new = isset($f['new']) && $f['new'] == 1;
            $null = (isset($f['mandetory']) && $f['mandetory'] == 1) ? 'NOT NULL' : 'NULL';
            if ($drop) {
                foreach (array($name, $old) as $n) if ($n !== '' && isset($cols[$n]) && !in_array($n, $this->reserved_fields, true)) {
                    $clauses[] = 'DROP `' . $this->_m9_tx_ident($n) . '`'; unset($cols[$n]); $expect[$n] = false; break;
                }
                continue;
            }
            if (!$new && $old !== '' && $old !== $name && isset($cols[$old]) && !isset($cols[$name])) {
                $clauses[] = 'CHANGE `' . $this->_m9_tx_ident($old) . '` `' . $this->_m9_tx_ident($name) . '` ' . $this->_m9_tx_type($f) . ' ' . $null;
                unset($cols[$old]); $cols[$name] = true; $expect[$old] = false; $expect[$name] = true;
            }
            $dt = isset($f['display_type']) ? (string)$f['display_type'] : '';
            if ($new && !isset($cols[$name]) && $dt !== '5' && $dt !== '7') {
                $clauses[] = 'ADD `' . $this->_m9_tx_ident($name) . '` ' . $this->_m9_tx_type($f) . ' ' . $null . ' AFTER `sr_no`';
                $cols[$name] = true; $expect[$name] = true;
            }
        }
        return array($clauses, $expect);
    }

    protected function _m9_tx_shadow_name($t, $tag = 's') {
        return 'm9' . $tag . '_' . substr(sha1($t . '|' . $this->m9Tx['id'] . '|' . $tag), 0, 24);
    }

    protected function _m9_tx_drop($t, $why) {
        try {
            if ($this->_m9_tx_table_exists($t)) $this->_m9_tx_ddl_run('DROP TABLE `' . $t . '`', $why . ' ' . $t);
            return true;
        } catch (Throwable $e) { return false; }
    }

    // The rows of the original table are copied into the empty shadow table. If anything changes
    // while the copy is in progress the row count/checksum differs and the submit stops before the
    // original schema or data is touched.
    protected function _m9_tx_copy_rows($from, $to) {
        $a = $this->_m9_tx_columns($from); $b = $this->_m9_tx_columns($to);
        $common = array();
        foreach (array_keys($a) as $c) if (isset($b[$c])) $common[] = '`' . str_replace('`', '', $c) . '`';
        if (!$common) throw new M9BuilderAbort('no_common_columns ' . $from);
        $this->_m9_tx_ddl_run('INSERT INTO `' . $to . '` (' . implode(', ', $common) . ') SELECT ' . implode(', ', $common) . ' FROM `' . $from . '`', 'copy ' . $from . ' to ' . $to);
        $pdo = $this->_m9_tx_pdo();
        $n1 = (int)$pdo->query('SELECT COUNT(*) FROM `' . $from . '`')->fetchColumn();
        $n2 = (int)$pdo->query('SELECT COUNT(*) FROM `' . $to . '`')->fetchColumn();
        if ($n1 !== $n2) throw new M9BuilderAbort('row_count_changed ' . $from);
        $c1 = $pdo->query('CHECKSUM TABLE `' . $from . '`')->fetch(PDO::FETCH_ASSOC);
        $c2 = $pdo->query('CHECKSUM TABLE `' . $to . '`')->fetch(PDO::FETCH_ASSOC);
        if (is_array($c1) && is_array($c2) && isset($c1['Checksum'], $c2['Checksum']) && $c1['Checksum'] !== null && $c2['Checksum'] !== null && (string)$c1['Checksum'] !== (string)$c2['Checksum']) {
            throw new M9BuilderAbort('row_checksum_changed ' . $from);
        }
        return true;
    }

    // DDL is applied to an empty shadow table. The original table is replaced by one atomic
    // RENAME only after the shadow has the expected columns and its copied rows match. A failure
    // before the rename leaves the original schema and data untouched; a failure after it is
    // undone by restoring the retained original table (see _m9_tx_rollback).
    protected function _m9_tx_apply_ddl() {
        $sum = array();
        foreach ($this->m9Tx['ddl'] as $p) {
            $t = $p['create'];
            if ($this->_m9_tx_table_exists($t)) continue;
            $sql = isset($p['raw_create']) ? $p['raw_create'] : $this->_m9_tx_create_sql($t, $p['default'], $p['sqld']);
            $this->m9Tx['created'][] = $t; // recorded first: if the statement ran but reports failure, undo still looks for it
            $this->_m9_tx_ddl_run($sql, 'create ' . $t);
            if (!$this->_m9_tx_table_exists($t)) throw new M9BuilderAbort('table_not_created ' . $t);
            $sum[] = 'create:' . $t;
        }
        $byTable = array();
        foreach ($this->m9Tx['ddl'] as $p) if ($p['alter'] !== '' && !empty($p['fields'])) $byTable[$p['alter']][] = $p['fields'];
        foreach ($byTable as $t => $lists) {
            $before = $this->_m9_tx_columns($t);
            list($clauses, $expect) = $this->_m9_tx_plan($before, $lists);
            if (!$clauses) continue;
            $shadow = $this->_m9_tx_shadow_name($t, 's');
            $backup = $this->_m9_tx_shadow_name($t, 'b');
            $this->_m9_tx_drop($shadow, 'stale shadow');
            $this->_m9_tx_drop($backup, 'stale backup');
            $this->m9Tx['shadow'][$t] = array('shadow' => $shadow, 'backup' => $backup, 'done' => false);
            try {
                $this->_m9_tx_ddl_run('CREATE TABLE `' . $shadow . '` LIKE `' . $t . '`', 'shadow create ' . $t);
                $this->_m9_tx_ddl_run('ALTER TABLE `' . $shadow . '` ' . implode(', ', $clauses), 'shadow alter ' . $t);
                $after = $this->_m9_tx_columns($shadow); $hit = 0;
                foreach ($expect as $c => $present) if (isset($after[$c]) === $present) $hit++;
                if ($hit !== count($expect)) throw new M9BuilderAbort('table_not_as_expected ' . $t);
                $this->_m9_tx_copy_rows($t, $shadow);
                $this->_m9_tx_ddl_run('RENAME TABLE `' . $t . '` TO `' . $backup . '`, `' . $shadow . '` TO `' . $t . '`', 'shadow rename ' . $t);
                $this->m9Tx['shadow'][$t]['done'] = true;
                if (!$this->_m9_tx_table_exists($t) || !$this->_m9_tx_table_exists($backup) || $this->_m9_tx_table_exists($shadow)) throw new M9BuilderAbort('table_rename_failed ' . $t);
                $sum[] = 'alter:' . $t . ':' . count($clauses);
            } catch (Throwable $e) {
                if (!$this->m9Tx['shadow'][$t]['done']) $this->_m9_tx_drop($shadow, 'shadow cleanup ' . $t);
                elseif ($this->_m9_tx_table_exists($backup) && $this->_m9_tx_table_exists($t) && !$this->_m9_tx_table_exists($shadow)) $this->m9Tx['shadow'][$t]['done'] = true;
                throw $e;
            }
        }
        return $sum ? implode(',', $sum) : 'none';
    }

    protected function _m9_tx_restore_shadow($t, $state, &$bad, &$undone) {
        $shadow = $state['shadow']; $backup = $state['backup'];
        try {
            if ($state['done']) {
                $quarantine = $this->_m9_tx_shadow_name($t, 'q');
                $this->_m9_tx_drop($quarantine, 'stale quarantine');
                $this->_m9_tx_ddl_run('RENAME TABLE `' . $t . '` TO `' . $quarantine . '`, `' . $backup . '` TO `' . $t . '`', 'shadow restore ' . $t);
                if ($this->_m9_tx_table_exists($backup) || !$this->_m9_tx_table_exists($t)) throw new M9BuilderAbort('restore_failed ' . $t);
                $this->_m9_tx_drop($quarantine, 'discard altered ' . $t);
                $undone++;
            } else {
                $this->_m9_tx_drop($shadow, 'discard shadow ' . $t);
            }
        } catch (Throwable $e) { $bad[] = 'table_not_restored:' . $t; }
    }

    protected function _m9_tx_drop_shadow_backups($tx) {
        if (!is_array($tx) || empty($tx['shadow'])) return;
        foreach ($tx['shadow'] as $t => $s) {
            if (empty($s['done'])) continue;
            try { $this->_m9_tx_drop($s['backup'], 'drop shadow backup ' . $t); } catch (Throwable $e) { }
        }
    }

    public function _m9_tx_commit() {
        if (!is_array($this->m9Tx)) throw new M9BuilderAbort('no_transaction');
        if ($this->m9Tx['answer'] === '') throw new M9BuilderAbort('generator_answer_not_checked');
        if (empty($this->m9Tx['files'])) throw new M9BuilderAbort('no_generated_files');
        if (!is_array(AppModel::$m9Journal)) throw new M9BuilderAbort('journal_missing');
        if (!empty(AppModel::$m9Journal['raw'])) throw new M9BuilderAbort('unjournaled_write ' . implode(',', array_unique(AppModel::$m9Journal['raw'])));
        $app = rtrim(APP, DS);
        foreach (array('Controller', 'Model', 'View') as $d) {
            clearstatcache(true, $app . DS . $d);
            if (is_link($app . DS . $d) || !is_dir($app . DS . $d) || !is_writable($app . DS . $d)) throw new M9BuilderAbort('folder_not_writable ' . $d);
        }
        // 1 every new file is written beside its target and read back
        $i = 0;
        foreach ($this->m9Tx['files'] as $rel => $content) {
            $target = $app . DS . str_replace('/', DS, $rel); $dir = dirname($target);
            clearstatcache(true, $dir); clearstatcache(true, $target);
            if (is_link($dir) || is_link($target) || is_dir($target)) throw new M9BuilderAbort('link_or_folder_at_target ' . $rel);
            if (!is_dir($dir)) { if (!@mkdir($dir, 0755)) throw new M9BuilderAbort('folder_not_created ' . $rel); $this->m9Tx['made'][] = $dir; }
            $lock = $this->_m9_file_lock($target);
            if ($lock === false) throw new M9BuilderAbort('file_busy ' . $rel);
            $this->m9Tx['fileLocks'][$rel] = $lock;
            $tmp = $dir . DS . '.m9b_' . $this->m9Tx['id'] . '_' . ($i++) . '.tmp';
            $h = @fopen($tmp, 'xb');
            if ($h === false) throw new M9BuilderAbort('file_not_written ' . $rel);
            $this->m9Tx['stage'][$rel] = array('tmp' => $tmp, 'target' => $target, 'backup' => null, 'placed' => false);
            $n = fwrite($h, $content); fflush($h); fclose($h);
            clearstatcache(true, $tmp);
            if ($n !== strlen($content) || @file_get_contents($tmp) !== $content) throw new M9BuilderAbort('file_not_written ' . $rel);
            @chmod($tmp, 0644);
        }
        // 2 the files being replaced are kept
        $bk = $app . DS . 'tmp' . DS . 'm9_builder' . DS . $this->m9Tx['id'];
        foreach ($this->m9Tx['stage'] as $rel => $s) {
            if (!file_exists($s['target'])) continue;
            if (!is_dir($bk) && !@mkdir($bk, 0755, true)) throw new M9BuilderAbort('backup_folder_not_created');
            $b = $bk . DS . str_replace('/', '__', $rel);
            if (!@copy($s['target'], $b) || hash_file('sha256', $b) !== hash_file('sha256', $s['target'])) throw new M9BuilderAbort('backup_failed ' . $rel);
            $this->m9Tx['stage'][$rel]['backup'] = $b;
        }
        // 3 the new files are moved into place (rename replaces the name, never writes through it)
        foreach ($this->m9Tx['stage'] as $rel => $s) {
            if (!@rename($s['tmp'], $s['target'])) throw new M9BuilderAbort('file_not_placed ' . $rel);
            $this->m9Tx['stage'][$rel]['placed'] = true;
        }
        // 4 the table, last: CREATE if missing, then one ALTER, then its columns read back
        $ddl = $this->_m9_tx_apply_ddl();
        $this->_m9_tx_drop_shadow_backups($this->m9Tx);
        $tx = $this->m9Tx; $this->m9Tx = null; $jr = AppModel::m9_journal_stop();
        $this->_m9_tx_unlock_files($tx);
        $this->_m9_tx_release_builder();
        $this->_m9_ac_log('builder_committed', $tx['action'] . ' ' . $tx['id'] . ' files=' . count($tx['stage']) . ' table=' . $ddl . ' ' . AppModel::m9_journal_summary($jr));
        $this->_clear_cake_cache();
        return true;
    }

    // Undo everything the submit did. Returns true when everything was put back.
    public function _m9_tx_rollback($why, $failure = false) {
        $tx = $this->m9Tx;
        if (!is_array($tx)) {
            if (is_array(AppModel::$m9Journal)) AppModel::m9_journal_stop();
            $this->_m9_tx_release_builder();
            return true;
        }
        $this->m9Tx = null;
        $bad = array(); $info = array(); $undone = 0; $jsum = AppModel::m9_journal_summary(AppModel::$m9Journal);
        try {
            // the table: an altered table is restored from its retained original before created tables are dropped
            if (!empty($tx['shadow'])) {
                foreach (array_reverse($tx['shadow'], true) as $t => $state) $this->_m9_tx_restore_shadow($t, $state, $bad, $undone);
            }
            foreach ($tx['created'] as $t) {
                try {
                    if (!$this->_m9_tx_table_exists($t)) continue;
                    $n = (int)$this->_m9_tx_pdo()->query('SELECT COUNT(*) FROM `' . $t . '`')->fetchColumn();
                    if ($n === 0) { $this->_m9_tx_ddl_run('DROP TABLE `' . $t . '`', 'undo ' . $t); $undone++; } else $bad[] = 'table_kept:' . $t;
                } catch (Exception $e) { $bad[] = 'table_not_dropped:' . $t; }
            }
            // files: put back what was replaced, remove what was new, remove temporary files
            foreach (array_reverse($tx['stage'], true) as $rel => $s) {
                if (!$s['placed']) { @unlink($s['tmp']); continue; }
                if ($s['backup'] !== null) {
                    $r = dirname($s['target']) . DS . '.m9r_' . $tx['id'] . '.tmp';
                    $ok = @copy($s['backup'], $r) && @rename($r, $s['target']) && hash_file('sha256', $s['target']) === hash_file('sha256', $s['backup']);
                    if (!$ok) { @unlink($r); $bad[] = 'file_not_restored:' . $rel; } else $undone++;
                } else {
                    if (@unlink($s['target'])) $undone++; else $bad[] = 'file_not_removed:' . $rel;
                }
            }
            foreach (array_reverse($tx['made']) as $d) @rmdir($d);
            foreach (array_reverse($tx['noted']) as $p) {
                clearstatcache(true, $p);
                if (is_file($p) && !is_link($p)) { if (@unlink($p)) $undone++; else $bad[] = 'file_not_removed:' . basename($p); }
                elseif (is_dir($p) && !is_link($p)) { if (@rmdir($p)) $undone++; else $bad[] = 'folder_not_removed:' . basename($p); }
            }
            // rows: only those this submit wrote (decision 397)
            try { $undone += AppModel::m9_journal_undo($this->_m9_tx_pdo(), $bad, $info); } catch (Throwable $e) { AppModel::m9_journal_stop(); $bad[] = 'rows_error'; }
        } finally {
            $this->m9Undo = array(); $this->m9MailQueue = array(); $this->m9DeferMail = false;
            $this->_m9_tx_unlock_files($tx);
            $this->_m9_tx_release_builder();
        }
        $this->_m9_ac_log($bad ? 'builder_rollback_incomplete' : 'builder_rolled_back', $tx['action'] . ' ' . $tx['id'] . ' ' . substr(preg_replace('/[^A-Za-z0-9 _.\/:-]/', '', (string)$why), 0, 120) . ' undone=' . $undone . ' ' . $jsum . ($info ? ' ' . implode(',', $info) : '') . ($bad ? ' ' . implode(',', $bad) : ''));
        if ($bad) {
            $this->Session->setFlash(__('The form was not changed, but some of this submit could not be undone (%s). Reference %s in tmp/logs/m9_access.log.', implode(', ', $bad), $tx['id']), 'default', array('class' => 'alert alert-danger'));
        } elseif ($failure || $undone > 0) {
            $this->Session->setFlash(__('The form was not changed (%s). Everything this submit had done was undone.', substr(preg_replace('/[^A-Za-z0-9 _.\/:-]/', '', (string)$why), 0, 120)), 'default', array('class' => 'alert alert-danger'));
        }
        return !$bad;
    }

    // Form delete actions (delete, delete_child) drop the form's own table: the only DDL besides the builder's commit (decision 395).
    protected function _m9_ddl_query($sql) {
        AppModel::$m9Ddl++;
        try { return $this->CustomTable->query($sql); } finally { AppModel::$m9Ddl--; }
    }

    // Owner's fourth review (13 Nov 2026): the form builder (add, add_child, recreate, recreate_child, create_master) changed the
    // table and saved the form before asking the vendor's generator for the form's code; when the generator refused (an expired API
    // subscription answers error 1) the table and the definition were already changed and the code was not. The submit is now
    // refused before any change under the same condition on which FlinkISO's own page tells the administrator not to submit
    // (elements/custom_forms.ctp: error 1 or no answer), asked through the same call that page makes.
    protected function _m9_generator_ready() {
        $r = $this->curl('post', 'custom_forms', 'elements', json_encode(array(array(), array())));
        $j = json_decode((string)$r, true);
        if (!is_array($j) || (isset($j['error']) && $j['error'] == 1)) {
            $why = (is_array($j) && isset($j['message']) && is_string($j['message'])) ? substr(preg_replace('/[^A-Za-z0-9 _.-]/', '', $j['message']), 0, 60) : 'no_answer';
            $this->_m9_ac_log('generator_unavailable', $this->request->params['controller'] . '/' . $this->request->params['action'] . ' ' . $why);
            return false;
        }
        return true;
    }

    protected function _m9_generator_refuse() {
        $this->Session->setFlash(__('The FlinkISO form generator is not available (API subscription or connection). Nothing was changed.'), 'default', array('class' => 'alert alert-danger'));
        $this->redirect(array('action' => 'index'));
    }

    public function _set_file($id = null,$type = null){        
        // before form submit
        // create call back folder
        $folder = WWW_ROOT . 'files' . DS . $this->Session->read('User.company_id') . DS . 'save_doc' . DS . $id;
        
        $callbackFolder = new Folder();
        $callbackFolder->create($folder);
        // generate key
        // move new onlyoffice document to new location
        if($type  == 'qc_documents'){
            $options = array('recursive' => - 1, 'conditions' => array('QcDocument.id' => $this->request->params['named']['qc_document_id']));
            $qcDoc = $this->CustomTable->QcDocument->find('first', $options);
            $this->set('qcDoc', $qcDoc);    

            $file_type = $qcDoc['QcDocument']['file_type'];
            $file_name = $qcDoc['QcDocument']['title'];
            $document_number = $qcDoc['QcDocument']['document_number'];
            $document_version = $qcDoc['QcDocument']['revision_number'];
            // $file_name = $document_number . '-' . $file_name . '-' . $document_version . '.' . $file_type;

            $file_name = $document_number . '-' . $file_name . '-' . $document_version;
            $file_name = $this->_clean_table_names($file_name);
            $file_name = $file_name . '.' . $file_type;

        }else if($type == 'processes'){
            $options = array('recursive' => - 1, 'conditions' => array('Process.id' => $id));
            $process = $this->CustomTable->Process->find('first', $options);
            $this->set('process', $process);    

            $file_type = $process['Process']['file_type'];
            $file_name = $process['Process']['file_name'];
        }
        
        
        
        $qcpfile = WWW_ROOT .  'files' . DS . $this->Session->read('User.company_id') . DS . $type . DS . $id . DS . $file_name;
        $tofile = WWW_ROOT .  'files' . DS . $this->Session->read('User.company_id') . DS . 'custom_tables' . DS . $id . DS . $file_name;
        $tofolder = WWW_ROOT .  'files' . DS . $this->Session->read('User.company_id') . DS . 'custom_tables' . DS . $id . DS;

        if(file_exists($tofolder)){
            unlink($tofolder);
        }


        if (file_exists($qcpfile)) {
            $folder = WWW_ROOT .  'files' . DS . $this->Session->read('User.company_id') . DS . 'custom_tables' . DS . $id;
            // owner's fifth review (decision 393): the working copy goes through _m9_safe_copy (no link followed, replaced by rename)
            if (true) {
                if (($m9c = $this->_m9_safe_copy(WWW_ROOT . 'files' . DS . $this->Session->read('User.company_id'), 'custom_tables/' . $id, $file_name, $qcpfile, true)) && $m9c[0]) {
                } else {
                    $this->Session->setFlash(__('File copy failed'));
                    $this->redirect(array('action' => 'index', 'qc_document_id' => $this->request->params['named']['qc_document_id'],'process_id' => $this->request->params['named']['process_id']));
                }
            } else {
                $this->Session->setFlash(__('Folder creation failed'));
                $this->redirect(array('action' => 'index', 'qc_document_id' => $this->request->params['named']['qc_document_id'],'process_id' => $this->request->params['named']['process_id']));
            }
        } else {        
            $this->Session->setFlash(__('You are creating HTML form without any document.'));            
        }
        $this->set('key', $this->_generate_onlyoffice_key($id));
    }
    /**
     * add method
     *
     * @return void
     */
    public function add_child() {

        if($this->Session->read('User.is_mr') == false){
            $this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
            $this->redirect(array('controller' => 'users', 'action' => 'access_denied',$n));
        }

        if(empty($this->request->params['named']['qc_document_id']) && empty($this->request->params['named']['process_id'])){
            $this->Session->setFlash(__('Select Document/Process first'), 'default', array('class' => 'alert alert-warning'));
            $this->redirect(array('controller' => 'custom_tables', 'action' => 'index',$n));   
        }

        if ($this->_show_approvals()) {
            $this->loadModel('User');
            $this->User->recursive = 0;
            $userids = $this->User->find('list', array('order' => array('User.name' => 'ASC'), 'conditions' => array('User.publish' => 1, 'User.soft_delete' => 0, 'User.is_approver' => 1)));
            $this->set(array('userids' => $userids));
            $this->set(array('showApprovals' => $this->_show_approvals()));
        }
        $this->_commons($this->Session->read('User.id'));
        // afert form submit
        if ($this->request->is('post')) {            
            if (false === $this->_m9_generator_ready()) $this->_m9_generator_refuse(); // owner's fourth review: no change unless the generator answers
            $this->_m9_tx_begin(); // owner's fifth review (decision 392): from here this submit changes everything or nothing

            $this->request->data['CustomTable']['password'] = Security::hash($this->request->data['CustomTable']['password'], 'md5', true);

            foreach($this->request->data['CustomTableFields'] as $fields){
                if($fields['default_field'] == 1){
                    $defaultfield = $this->_clean_table_names($fields['field_name']);
                }
            }

            if ($defaultfield == '') {
                $this->Session->setFlash(__('Default field missing'));
                $this->redirect(array('action' => 'add_child', $id,'custom_table_id'=>$this->request->params['named']['custom_table_id'],  'qc_document_id' => $this->request->params['named']['qc_document_id']));

            }

            $fieldTypes = $this->CustomTable->customArray['fieldTypes'];
            $friendlyName = $this->request->data['CustomTable']['name'];

            if($this->request->params['named']['qc_document_id'] != ''){
                $qcDocument = $this->CustomTable->QcDocument->find('first', array('recursive' => 0, 'conditions' => array('QcDocument.id' => $this->request->params['named']['qc_document_id']),));
                $table_name_version = $this->_make_child_table_name($this->request->params['named']['qc_document_id'],'qc_documents',$customTable['CustomTable']['linked']);
            // for processes                
            }else if($this->request->params['named']['process_id'] != ''){
                $process = $this->CustomTable->Process->find('first', array('recursive' => 0, 'conditions' => array('Process.id' => $this->request->params['named']['process_id']),));
                $table_name_version = $this->_make_child_table_name($this->request->params['named']['process_id'],'processes',$customTable['CustomTable']['linked']);                
            }else{
                
            }

            
            $this->request->data['CustomTable']['table_name'] = $table_name_version[0];
            $this->request->data['CustomTable']['table_version'] = $table_name_version[1];
            $table_name = $table_name_version[0];
            //prepare sql
            $this->request->data['CustomTable']['fields'] = json_encode($this->request->data['CustomTableFields']);
            $this->request->data['CustomTable']['system_table_id'] = $this->_get_system_table_id();
            
            $this->CustomTable->create();
            
            $customTable = $this->CustomTable->find('first', array('conditions' => array('CustomTable.id' => $this->request->data['CustomTable']['custom_table_id'])));
            $hasMany = json_decode($customTable['CustomTable']['has_many'], true);
            $hasMany[] = array('table_name' => $this->request->data['CustomTable']['table_name'], 'friendly_name' => $this->request->data['CustomTable']['name'], 'table_version' => $this->request->data['CustomTable']['table_version']);
            

            $customTable['CustomTable']['has_many'] = json_encode($hasMany);            

            $newFields = array();
            foreach($this->request->data['CustomTableFields'] as $fields){
                if($fields['drop'] == 1){
                    unset($fields);
                }else{
                    $fields['who_can_edit'] = json_encode($fields['who_can_edit']);
                    $fields['show_comments'] = base64_encode($fields['show_comments']);
                    $fields['field_label'] = Inflector::humanize($this->_clean_table_names($fields['field_label']));
                    $fields['field_label'] = base64_encode($fields['field_label']);
                    $newFields[] = $fields;                    
                }                
            }
            
            if(is_array($newFields)){
                $this->request->data['CustomTable']['fields'] = json_encode($newFields);    
                $this->request->data['CustomTableFields'] = $newFields;
            }
            
            $customTable['CustomTable']['has_many_existing'] = json_encode($hasMany);
            
            if ($this->CustomTable->save($this->request->data)) {
                
                $customTable['CustomTable']['has_many_existing'] = json_encode($hasMany);
                $data = array($this->request->data,$table_name,$friendlyName,$defaultfield,$customTable);
                $result = $this->curl('post','custom_forms','create_child',$data);
                $result = json_decode($result,true);
                $this->_m9_tx_check_answer($result, 'create_child'); // decision 392: the whole answer is checked before it is used
                

                if($result['error'] == 1){
                    echo "Something went wrong. Please try again";
                }else{
                    $result = json_decode($result['response']['finalResult'],true);
                    
                    if($result['controller']){
                        $controller_file_name = Inflector::pluralize(Inflector::Classify($table_name)) . 'Controller.php';
                        $folder = APP . 'Controller';
                        $file = $folder . DS . $controller_file_name;
                        $this->_write_to_file($folder,$file,$result['controller']);
                    }

                    if($result['model']){

                        $name = Inflector::Classify($table_name) . '.php';
                        $folder = APP . 'Model';
                        $file = $folder . DS . $name;
                        $this->_write_to_file($folder,$file,$result['model']);
                    }

                    // $viewCode = json_decode($result['viewFile'],true);

                    if($result['index']){
                        $folder = APP . 'View' . DS . Inflector::pluralize(Inflector::classify($table_name));        
                        $modelFolder = new Folder();
                        $file = $folder . DS . 'index.ctp';
                        $this->_write_to_file($folder,$file,$result['index']);
                    }

                    $addCode = json_decode($result['formFile'],true);
                    
                    $folder = APP . 'View' . DS . Inflector::pluralize(Inflector::classify($table_name));        
                    $modelFolder = new Folder();
                    
                    foreach($addCode as $file => $code){
                        $file = $folder . DS . $file.'.ctp';
                        $this->_write_to_file($folder,$file,$code);
                    }
                    
                    if($result['parentModelFile']){

                        $name = Inflector::Classify($customTable['CustomTable']['table_name']) . '.php';
                        $folder = APP . 'Model';
                        $file = $folder . DS . $name;
                        $this->_write_to_file($folder,$file,$result['parentModelFile']);
                    }                    
                }



                if($this->request->data['CustomTable']['qc_document_id']){
                    $sqld.= "`qc_document_id` varchar(36) NOT NULL DEFAULT '" . $this->request->data['CustomTable']['qc_document_id'] . "',
                    ";
                }else if($this->request->data['CustomTable']['process_id']){
                    $sqld.= "`process_id` varchar(36) NOT NULL DEFAULT '" . $this->request->data['CustomTable']['process_id'] . "',
                    ";
                    $sqld.= "`qc_document_id` varchar(36) NULL',
                    ";
                }
                
                $sqld.= "`custom_table_id` varchar(36) NOT NULL DEFAULT '" . $this->CustomTable->id . "',";

                $sqlresult = $this->_add_new_table($table_name,$defaultfield,$sqld,$this->request->data['CustomTableFields']);
                $this->_m9_approval_begin();
                if ($this->_show_approvals() && !$this->_save_approvals($this->CustomTable->id)) throw new M9BuilderAbort('approval_save_failed');
                $this->_m9_tx_commit(); // decision 392
                $this->_m9_approval_commit(); // mail leaves only after the form commit succeeded
                $this->Session->setFlash(__('Form created'));
                $this->redirect(array('action' => 'index', 'qc_document_id' => $this->request->params['named']['qc_document_id']));
            } else {
                $this->Session->setFlash(__('The custom table could not be saved. Please, try again.'));
            }
        }
        

        if ($this->request->params['named']['qc_document_id'] || $this->request->params['named']['process_id']) {
            
            // generate table name
            // for documets
            if($this->request->params['named']['qc_document_id'] != ''){
                $qcDocument = $this->CustomTable->QcDocument->find('first', array('recursive' => 0, 'conditions' => array('QcDocument.id' => $this->request->params['named']['qc_document_id']),));
                $table_name_version = $this->_make_child_table_name($this->request->params['named']['qc_document_id'],'qc_documents',$customTable['CustomTable']['linked']);
                $this->set('qcDocument', $qcDocument);
                $this->set('table_name', $table_name_version[0]);
                $this->set('table_version', $table_name_version[1]);
                $this->_set_file($this->request->params['named']['qc_document_id'],'qc_documents');

            // for processes                
            }else if($this->request->params['named']['process_id'] != ''){
                $process = $this->CustomTable->Process->find('first', array('recursive' => 0, 'conditions' => array('Process.id' => $this->request->params['named']['process_id']),));
                $table_name_version = $this->_make_child_table_name($this->request->params['named']['process_id'],'processes',$customTable['CustomTable']['linked']);
                $this->set('process', $process);
                $this->set('table_name', $table_name_version[0]);
                $this->set('table_version', $table_name_version[1]);
                $this->_set_file($this->request->params['named']['process_id'],'processes');
            // if none
            }else{
                
            }

            if(!$qcDocument && !$process){
                $this->Session->setFlash(__('Select Document/Process first'), 'default', array('class' => 'alert alert-warning'));
                $this->redirect(array('controller' => 'custom_tables', 'action' => 'index',$n));   
            }
            
        } else {
            $this->Session->setFlash(__('Chose document first to create table. Tables are linked with documents and document must be present before adding table.'));
            $this->redirect(array('controller' => 'qc_documents', 'action' => 'index'));
        }
        $this->set('key', $this->_generate_onlyoffice_key($qcDocument['QcDocument']['id']));
        $customTable = $this->CustomTable->find('first',array('conditions'=>array('CustomTable.id'=>$this->request->params['named']['custom_table_id']),'recursive'=>-1));
        $this->set('customTable',$customTable);
    }
    /**
     * edit method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    public function get_linked_to_fields($model = null) {
        $model = $this->request->params['named']['model'];
        $this->loadModel($model);
        $this->$model->schema();
        $this->set('f', $f);
        $this->set('fields', array_keys($this->$model->schema()));
    }
    public function delete($id = null) {
        if($this->Session->read('User.is_mr') == false){
            $this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
            $this->redirect(array('controller' => 'users', 'action' => 'access_denied',$n));
        }

        if ($this->request->is('post') || $this->request->is('put')) {
            if (empty($this->request->data['CustomTable']['password'])) {
                $this->Session->setFlash(__('Enter password'));
                $this->redirect(array('action' => 'index'));
            } else {                
                $found = $this->check_before_delete($this->data['CustomTable']['id']);
                if(!$found){            
                    $customTable = $this->CustomTable->find('first', array('conditions' => array('CustomTable.password' => trim(Security::hash($this->data['CustomTable']['password'], 'md5', true)), 'CustomTable.id' => $this->data['CustomTable']['id'])));

                    if ($customTable) {                            
                            // check if this field exists in any other tables
                        $allTables = $this->CustomTable->find('all',array('recursive'=>-1));
                        foreach($allTables as $allTable){
                            $model = $allTable['CustomTable']['table_name'];
                            $model = Inflector::classify($model);
                            try{
                                $this->loadModel($model);                    
                                if(in_array(Inflector::classify($customTable['CustomTable']['name']),array_keys($this->$model->belongsTo) )){
                                    $this->Session->setFlash(__('Can not delete this table due to dependency on '. $allTable['CustomTable']['name'] . ' table'));
                                    $this->redirect(array('action' => 'view',$this->data['CustomTable']['id']));    
                                }    
                            }catch(Exception $e){

                            }
                        }

                        if ($customTable['CustomTable']['table_locked'] == 0) {
                            $this->Session->setFlash(__('Can not recreate/delete locked table'));
                            $this->redirect(array('action' => 'index'));
                        }

                            // also delete child
                        foreach (json_decode($customTable['CustomTable']['has_many'], true) as $childs) {                        
                                // delete files
                            $folder = APP . 'Controller';
                            $name = Inflector::pluralize(Inflector::Classify($childs['table_name'])) . 'Controller.php';
                            if ($name) {
                                unlink($folder . DS . $name);
                            }
                            $folder = APP . 'Model';
                            $name = Inflector::singularize(Inflector::Classify($childs['CustomTable']['table_name'])) . '.php';
                            if ($name) {
                                unlink($folder . DS . $name);
                            }
                            $folder = APP . 'View';
                            $name = Inflector::pluralize(Inflector::Classify($childs['table_name']));
                            $folder = APP . 'View' . DS . Inflector::pluralize(Inflector::classify($childs['table_name']));
                            if ($folder != APP . 'View') {
                                $modelFolder = new Folder();
                                $modelFolder->delete($folder);
                                $this->CustomTable->delete(array('CustomTable.id' => $id));
                            }
                            $sql = 'DROP TABLE `' . $childs['table_name'] . '`';
                            try {
                                $q = $this->_m9_ddl_query($sql);
                            }
                            catch(Exception $e) {
                            }
                            $this->CustomTable->delete(array('CustomTable.table_name' => $childs['table_name']));
                        }   

                        
                        if ($customTable['CustomTable']['name']) {
                                // delete files
                            $folder = APP . 'Controller';
                            $name = Inflector::pluralize(Inflector::Classify($customTable['CustomTable']['table_name'])) . 'Controller.php';
                            if ($name) {
                                unlink($folder . DS . $name);
                            }
                            $folder = APP . 'Model';
                            $name = Inflector::singularize(Inflector::Classify($customTable['CustomTable']['table_name'])) . '.php';
                            if ($name) {
                                unlink($folder . DS . $name);
                            }
                            $folder = APP . 'View';
                            $name = Inflector::pluralize(Inflector::Classify($customTable['CustomTable']['table_name']));
                            $folder = APP . 'View' . DS . Inflector::pluralize(Inflector::classify($customTable['CustomTable']['table_name']));
                            if ($folder != APP . 'View') {
                                $modelFolder = new Folder();
                                $modelFolder->delete($folder);
                                $this->CustomTable->delete(array('CustomTable.id' => $id));
                            }
                            
                            $path = Configure::read('files') .  DS . 'custom_tables' . DS . $customTable['CustomTable']['id'];
                            $filesFolder = new Folder($path);
                            $filesFolder->delete();

                                // delete all records from files table
                            $this->loadModel('File');
                            $this->File->delete(array('File.controller' => $customTable['CustomTable']['table_name']));
                            
                            $sql = 'DROP TABLE `' . $customTable['CustomTable']['table_name'] . '`';

                            try {
                                $q = $this->_m9_ddl_query($sql);
                            }
                            catch(Exception $e) {
                                
                            }

                                // must also delete approvals
                            $this->loadModel('Approval');
                            
                                // get child tables
                            $child_tables = $this->CustomTable->find('all',array('conditions'=>array('CustomTable.custom_table_id'=>$this->data['CustomTable']['id'])));
                            foreach($child_tables as $child_table){
                                    // delete files
                                $folder = APP . 'Controller';
                                $name = Inflector::pluralize(Inflector::Classify($child_table['CustomTable']['table_name'])) . 'Controller.php';
                                if ($name) {
                                    unlink($folder . DS . $name);
                                }
                                $folder = APP . 'Model';
                                $name = Inflector::singularize(Inflector::Classify($child_table['CustomTable']['table_name'])) . '.php';
                                if ($name) {
                                    unlink($folder . DS . $name);
                                }
                                $folder = APP . 'View';
                                $name = Inflector::pluralize(Inflector::Classify($child_table['CustomTable']['table_name']));
                                $folder = APP . 'View' . DS . Inflector::pluralize(Inflector::classify($child_table['CustomTable']['table_name']));
                                if ($folder != APP . 'View') {
                                    $modelFolder = new Folder();
                                    $modelFolder->delete($folder);
                                    $this->CustomTable->delete(array('CustomTable.id' => $id));
                                }
                                    // delete all folders in files folder
                                $path = Configure::read('files') . DS . 'custom_tables' . DS . $child_table['CustomTable']['id'];
                                $filesFolder = new Folder($path);                                    
                                $filesFolder->delete();
                                
                                $path = Configure::read('files') . DS . 'custom_tables' . DS . $child_table['CustomTable']['table_name'];
                                $filesFolder = new Folder($path);
                                $filesFolder->delete();
                                    // delete all records from files table
                                $this->loadModel('File');
                                $this->File->delete(array('File.controller' => $child_table['CustomTable']['table_name']));
                                
                                $sql = 'DROP TABLE `' . $child_table['CustomTable']['table_name'] . '`';
                                try {                                
                                    $q = $this->_m9_ddl_query($sql);
                                }
                                catch(Exception $e) {                                        
                                }
                                
                                $this->Approval->deleteAll(array('Approval.controller_name'=>$child_table['CustomTable']['table_name']),true);
                            }

                                // delete approvals

                                // delete tables from custom_tables
                            $this->CustomTable->deleteAll(array('CustomTable.custom_table_id'=>$this->data['CustomTable']['id']));


                            try{
                                $this->CustomTable->delete($customTable['CustomTable']['id']);
                            }catch (Exception $e){
                                
                            }
                            try{
                                $this->Approval->deleteAll(array('Approval.controller_name'=>$customTable['CustomTable']['table_name']),true);
                            }catch (Exception $e){
                                
                            }
                            

                            $this->CustomTable->CustomTrigger->deleteAll(array('CustomTrigger.custom_table_id'=>$customTable['CustomTable']['id']));
                            $this->CustomTable->RecordLock->deleteAll(array('RecordLock.lock_table_id'=>$customTable['CustomTable']['id']));
                            $this->CustomTable->CustomTableTask->deleteAll(array('CustomTableTask.custom_table_id'=>$customTable['CustomTable']['id']));
                            $this->CustomTable->GraphPanel->deleteAll(array('GraphPanel.custom_table_id'=>$customTable['CustomTable']['id']));
                            $this->CustomTable->File->deleteAll(array('File.custom_table_id'=>$customTable['CustomTable']['id']));
                            $this->CustomTable->CustomCode->deleteAll(array('CustomCode.custom_table_id'=>$customTable['CustomTable']['id']));
                            $this->CustomTable->CustomTableProcess->deleteAll(array('CustomTableProcess.custom_table_id'=>$customTable['CustomTable']['id']));

                            $this->Session->setFlash(__('Table Deleted'));
                            $this->redirect(array('action' => 'index'));
                        }

                    } else {
                        $this->Session->setFlash(__('Incorrect Password'));
                        $this->redirect(array('action' => 'delete', $this->request->data['CustomTable']['id']));
                    }
                }else{
                    $this->Session->setFlash(__('Can not delete this table.'));
                    $this->Set('found',$found);
                }
            }            
        }        
        
    }
    public function recreate($id = null,$skip = false, $data = null) {
        $this->_clear_cake_cache();
        if($this->Session->read('User.is_mr') == false){
            $this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
            $this->redirect(array('controller' => 'users', 'action' => 'access_denied',$n));
        }

        if($skip == true){
            $this->request->data = $data;
        }

        if ($this->request->is('post') || $this->request->is('put') || $skip == true) {
            if (false === $this->_m9_generator_ready()) $this->_m9_generator_refuse(); // owner's fourth review: no change unless the generator answers
            $this->_m9_tx_begin(); // owner's fifth review (decision 392): from here this submit changes everything or nothing

            $updatesql = '';
            $dropsql = '';
            $addsql = '';            
            foreach($this->request->data['CustomTableFields'] as $fields){
                if($fields['default_field'] == 1){
                    $defaultfield = $this->_clean_table_names($fields['field_name']);                  
                }
            }

            if ($defaultfield == '') {
                $this->Session->setFlash(__('Default field missing'));
                $this->redirect(array('action' => 'recreate', $id, 'qc_document_id' => $this->request->params['named']['qc_document_id']));
            }

            $fieldTypes = $this->CustomTable->customArray['fieldTypes'];
            $table_name = $this->request->data['CustomTable']['table_name'];
            
            $newFields = array();
            foreach($this->request->data['CustomTableFields'] as $fields){
                if($fields['drop'] == 1){
                    $toDrop[] = $fields;                    
                    unset($fields);
                }else{
                    if($fields['who_can_edit'])$fields['who_can_edit'] = json_encode($fields['who_can_edit']);
                    if($fields['show_comments'])$fields['show_comments'] = base64_encode($fields['show_comments']);
                    $fields['field_name'] = $this->_clean_table_names($fields['field_name']);
                    // $fields['field_label'] = Inflector::humanize($this->_clean_table_names($fields['field_label']));
                    if($fields['field_label'])$fields['field_label'] = base64_encode($fields['field_label']);
                    $newFields[] = $fields;                    
                }

                if($fields['display_type'] == 3 || $fields['display_type'] == 4){
                    $belongsTo[$fields['field_name']] = $fields['linked_to'];
                }
            }
            $sqlresult = $this->_add_new_table($table_name,$defaultfield,null,$toDrop);
            
            if(is_array($newFields)){
                $this->request->data['CustomTable']['fields'] = json_encode($newFields);    
                $this->request->data['CustomTableFields'] = $newFields;
            }
            
            $this->request->data['CustomTable']['system_table_id'] = $this->_get_system_table_id();
            if($skip == true){
                
            }else{
                $this->request->data['CustomTable']['password'] = Security::hash($this->request->data['CustomTable']['password'], 'md5', true);
            }
            
            $this->CustomTable->create();

            $this->request->data['CustomTable']['belongs_to'] = json_encode($belongsTo);
            $belongsTo = null;
            if ($this->CustomTable->save($this->request->data,false)) {

                // update approval process 
                $this->loadModel('ApprovalProcess');
                $apprpvalProcess = $this->ApprovalProcess->find('first',array('conditions'=>array('ApprovalProcess.id'=>$this->request->data['CustomTable']['approval_process_id'])));
                if($apprpvalProcess){
                    $applicableTos = json_decode($apprpvalProcess['ApprovalProcess']['applicable_to'],true);
                    if(!in_array($this->request->data['CustomTable']['id'], $applicableTos)){
                        $applicableTos[] = $this->request->data['CustomTable']['id'];
                        $apprpvalProcess['ApprovalProcess']['applicable_to'] = json_encode($applicableTos);
                        $this->ApprovalProcess->create();
                        $this->ApprovalProcess->save($apprpvalProcess,false);
                    }
                }
                unset($this->request->data['History']);
                unset($this->request->data['CustomTable']['pre_fields']);
                
                $friendlyName = $this->request->data['CustomTable']['name'];
                $hasMany = $this->request->data['CustomTable']['has_many'];

                // Owner's fifth review (16 Nov 2026), decision 396: no chmod 0777; the folders must already be writable by the site's PHP user.
                foreach (array('Controller', 'Model', 'View') as $m9d) if (!is_writable(APP . $m9d)) throw new M9BuilderAbort('folder_not_writable ' . $m9d);

                // run update quieries
                $sqlresult = $this->_add_new_table($table_name,$defaultfield,null,$this->request->data['CustomTableFields']);
                $thisModel = Inflector::classify($table_name);
                
                $this->loadModel($thisModel);
                $findChilds = $this->CustomTable->find('all',array(
                    'conditions'=>array(
                        'CustomTable.custom_table_id'=>$this->CustomTable->id
                    ),
                    'recursive'=>-1,
                    'fields'=>array(
                        'CustomTable.id',
                        'CustomTable.table_name',
                        'CustomTable.name',
                        'CustomTable.custom_table_id',
                        'CustomTable.table_version',
                    ))
                );

                if($findChilds){
                    $hasMany = array();
                    foreach($findChilds as $findChild){                        
                        $hasMany[] = array('table_name'=>$findChild['CustomTable']['table_name'],'friendly_name'=>$findChild['CustomTable']['name'],'table_version'=>$findChild['CustomTable']['table_version']);
                    }                    
                }
                
                $data = array($this->request->data,$table_name,$friendlyName,$defaultfield,json_encode($hasMany));
                $result = $this->curl('post','custom_forms','create',$data);
                $result = json_decode($result,true);
                $this->_m9_tx_check_answer($result, 'create'); // decision 392: the whole answer is checked before it is used
                
                if(!$result || $result['error'] == 1){
                    $this->Session->setFlash(__('Something went wrong. Please try again'));
                    $this->redirect(array('action' => 'recreate',$this->request->data['CustomTable']['id']));
                }else{
                    $result = json_decode($result['response']['finalResult'],true);
                    if($result['controller']){
                        $controller_file_name = Inflector::pluralize(Inflector::Classify($table_name)) . 'Controller.php';
                        $folder = APP . 'Controller';
                        $file = $folder .  DS . $controller_file_name;
                        $this->_write_to_file($folder,$file,$result['controller']);                                
                    }

                    if($result['model']){

                        $name = Inflector::Classify($table_name) . '.php';
                        $folder = APP . 'Model';
                        $file = $folder . DS . $name;
                        $this->_write_to_file($folder,$file,$result['model']);
                    }                            

                    $viewCode = json_decode($result['viewFile'],true);

                    $folder = APP . 'View' . DS . Inflector::pluralize(Inflector::classify($table_name));        
                    $modelFolder = new Folder();
                    $this->_m9_mode($folder);
                    
                    if($viewCode['index']){
                        $file = $folder . DS . 'index.ctp';
                        $this->_write_to_file($folder,$file,$viewCode['index']);
                    }

                    if($viewCode['index']){
                        $file = $folder . DS . 'view.ctp';                                
                        $this->_write_to_file($folder,$file,$viewCode['view']);
                        
                    }

                    if($viewCode['json']){
                        $file = $folder . DS . 'json.ctp';                                
                        $this->_write_to_file($folder,$file,$viewCode['json']);
                        
                    }

                    if($viewCode['xml']){
                        $file = $folder . DS . 'xml.ctp';                                
                        $this->_write_to_file($folder,$file,$viewCode['xml']);
                        
                    }

                    $addCode = json_decode($result['formFile'],true);

                    if($addCode['add']){
                        $file = $folder . DS . 'add.ctp';                                
                        $this->_write_to_file($folder,$file,$addCode['add']);
                        
                    }

                    if($addCode['edit']){
                        $file = $folder . DS . 'edit.ctp';                                
                        $this->_write_to_file($folder,$file,$addCode['edit']);
                        
                    }                    
                }

                $doApprovals = ($skip == false && $this->_show_approvals());
                $this->_m9_approval_begin();
                if ($doApprovals && !$this->_save_approvals($this->CustomTable->id)) throw new M9BuilderAbort('approval_save_failed');
                $this->_m9_tx_commit(); // decision 392
                $this->_m9_approval_commit(); // mail leaves only after the form commit succeeded
                $this->_clear_cake_cache();
                if($skip == false) {
                    $this->redirect(array('action' => 'recreate',$this->request->data['CustomTable']['id']));    
                }
                
            } else {
                if($skip == false) {
                    $this->Session->setFlash(__('The custom table could not be saved. Please, try again.'));
                }else{
                    echo "The custom table could not be saved. Please, try again.";
                    exit;
                }
            }
        }
        
        $customTable = $this->CustomTable->find('first', array('recursive' => 0, 'conditions' => array('CustomTable.id' => $id)));
        $customTable['CustomTable']['fields'] = str_replace('\/','',$customTable['CustomTable']['fields']);
        
        // find child tables
        $customTable['ChildTables'] = $this->CustomTable->find('all',array(
            'conditions'=>array('CustomTable.custom_table_id'=>$customTable['CustomTable']['id']),
            'fields'=>array('CustomTable.id','CustomTable.name','CustomTable.fields','CustomTable.table_name'),
            'recursive'=>-1)
    );
        
        $this->set('customTable', $customTable);
        $this->request->data = $customTable;
        
        $qcDocument = $this->CustomTable->QcDocument->find('first', array('recursive' => 0, 'conditions' => array('QcDocument.id' => $customTable['CustomTable']['qc_document_id']),));
        $process = $this->CustomTable->Process->find('first', array('recursive' => 0, 'conditions' => array('Process.id' => $customTable['CustomTable']['process_id']),));
        
        $this->set('qcDocument', $qcDocument);
        $this->set('process', $process);
        if ($this->_show_approvals()) {
            $this->loadModel('User');
            $this->User->recursive = 0;
            $userids = $this->User->find('list', array('order' => array('User.name' => 'ASC'), 'conditions' => array('User.publish' => 1, 'User.soft_delete' => 0, 'User.is_approver' => 1)));
            $this->set(array('userids' => $userids));
            $this->set(array('showApprovals' => $this->_show_approvals()));
        }

        $fields = json_decode($customTable['CustomTable']['fields'],true);
        foreach($fields as $field){
            $fieldDetails[] = $field;
        }        
        
        $this->set('fieldDetails', $fieldDetails);

        $key = $this->_generate_onlyoffice_key($customTable['CustomTable']['id'] . date('Ymdhis'));
        $this->set('filekey', $customTable['CustomTable']['file_key']);

        $this->_commons($this->Session->read('User.id'));  
        $this->set('linkedTosWithDisplay',$this->_returnDetaultField($fields));

        if($qcDocument['QcDocument']['parent_document_id']){
            $parent = $this->CustomTable->find('first',array(
                'recursive'=>-1,
                'fields'=>array('CustomTable.id','CustomTable.fields'),
                'conditions'=>array('CustomTable.qc_document_id'=>$qcDocument['QcDocument']['parent_document_id'])));

            if($parent){
                $parentFields = json_decode($parent['CustomTable']['fields'],true);                    
                foreach ($parentFields as $f) {
                    
                    if($f["data_type"] == 'radio')$fieldNames[$f["field_name"]] = $f["field_name"];
                    $fields[$f["field_name"]] = $f;
                    $this->set("fieldNames", $fieldNames);
                    $this->set("fields", $fields);   
                    $this->set("parent",$parent);                     
                }
            }

        }
    }
    
    public function _get_hasManies($data = null) {
        $parent = $this->CustomTable->find('first', array('conditions' => array('CustomTable.id' => $data['CustomTable']['custom_table_id'])));
        if ($parent) {
            
            $childs = $this->CustomTable->find('all', array('recursive' => - 1, 'fields' => array('CustomTable.id', 'CustomTable.table_name', 'CustomTable.table_version', 'CustomTable.name'), 'conditions' => array('CustomTable.custom_table_id' => $parent['CustomTable']['id'])));
        }
        foreach ($childs as $child) {
            $existingHasManies[] = array('table_name' => $child['CustomTable']['table_name'], 'friendly_name' => $child['CustomTable']['name'], 'table_version' => $child['CustomTable']['table_version']);
        }
        $existingHasManies[] = array('table_name' => $data['CustomTable']['table_name'], 'friendly_name' => $data['CustomTable']['name'], 'table_version' => $data['CustomTable']['table_version']);
        $existingHasManies = array_unique($existingHasManies, SORT_REGULAR);
        $this->CustomTable->read(null, $parent['CustomTable']['id']);
        $this->CustomTable->set('has_many', json_encode($existingHasManies));
        $this->CustomTable->save();        
        return $existingHasManies;
    }
    
    public function recreate_child($id = null,$skip = null,$data = null) {
        if($this->Session->read('User.is_mr') == false){
            $this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
            $this->redirect(array('controller' => 'users', 'action' => 'access_denied',$n));
        }

        if($skip == true){
            $this->request->data = $data;
        }

        if ($this->request->is('post') || $this->request->is('put')) {
            if (false === $this->_m9_generator_ready()) $this->_m9_generator_refuse(); // owner's fourth review: no change unless the generator answers
            $this->_m9_tx_begin(); // owner's fifth review (decision 392): from here this submit changes everything or nothing
            
            foreach ($this->request->data['CustomTableFields'] as $chkd) {
                if ($chkd['field_name']) $chkarray[] = $chkd['field_name'];
            }

            foreach($this->request->data['CustomTableFields'] as $fields){
                if($fields['default_field'] == 1){
                    $defaultfield = $this->_clean_table_names($fields['field_name']);
                    // $sqld = "`".$defaultfield."` varchar(255) NOT NULL,";
                }
            }

            if ($defaultfield == '') {
                $this->Session->setFlash(__('Default field missing'));
                $this->redirect(array('action' => 'recreate_child', $id, 'qc_document_id' => $this->request->params['named']['qc_document_id']));
            }

            $fieldTypes = $this->CustomTable->customArray['fieldTypes'];
            $table_name = $this->request->data['CustomTable']['table_name'];

            $newFields = array();
            foreach($this->request->data['CustomTableFields'] as $fields){
                if($fields['drop'] == 1){
                    $toDrop[] = $fields;                    
                    unset($fields);
                }else{
                    if($fields['who_can_edit'])$fields['who_can_edit'] = json_encode($fields['who_can_edit']);
                    if($fields['show_comments'])$fields['show_comments'] = base64_encode($fields['show_comments']);
                    $fields['field_name'] = $this->_clean_table_names($fields['field_name']);
                    // $fields['field_label'] = Inflector::humanize($this->_clean_table_names($fields['field_label']));
                    if($fields['field_label'])$fields['field_label'] = base64_encode($fields['field_label']);
                    $newFields[] = $fields;
                }

                if($fields['display_type'] == 3 || $fields['display_type'] == 4){
                    $belongsTo[$fields['field_name']] = $fields['linked_to'];
                }
            }

            $this->request->data['CustomTable']['belongs_to'] = json_encode($belongsTo);
            $belongsTo = null;

            $sqlresult = $this->_add_new_table($table_name,$defaultfield,null,$toDrop);
            
            if(is_array($newFields)){
                $this->request->data['CustomTable']['fields'] = json_encode($newFields);    
                $this->request->data['CustomTableFields'] = $newFields;
            }           
            
            $this->request->data['CustomTable']['system_table_id'] = $this->_get_system_table_id();
            if($skip == true){
                $this->request->data = $data;
            }else{
                $this->request->data['CustomTable']['password'] = Security::hash($this->request->data['CustomTable']['password'], 'md5', true);    
            }
            
            $customTable = $this->CustomTable->find('first', array('recursive'=>-1, 'conditions' => array('CustomTable.id' => $this->request->data['CustomTable']['custom_table_id'])));
            
            $hasMany = json_decode($customTable['CustomTable']['has_many'], true);
            $model = Inflector::classify($customTable['CustomTable']['table_name']);
            
            $this->loadModel($model);
            $hasManyExisting = $this->$model->hasMany;
            
            $hasMany[] = array('table_name' => $this->request->data['CustomTable']['table_name'], 'friendly_name' => $this->request->data['CustomTable']['name'], 'table_version' => $this->request->data['CustomTable']['table_version']);

            $this->CustomTable->create();
            if ($this->CustomTable->save($this->request->data)) {

                unset($this->request->data['History']);
                unset($this->request->data['CustomTable']['pre_fields']);
                
                $friendlyName = $this->request->data['CustomTable']['name'];
                $table_name_version = $this->request->data['CustomTable']['table_name'];
                
                $hasManyExisting = $this->_get_hasManies($this->request->data);
                
                // recreate                
                $customTable['CustomTable']['has_many_existing'] = json_encode($hasManyExisting);
                $data = array($this->request->data,$table_name,$friendlyName,$defaultfield,$customTable);
                
                $result = $this->curl('post','custom_forms','create_child',$data);
                $result = json_decode($result,true);
                $this->_m9_tx_check_answer($result, 'create_child'); // decision 392: the whole answer is checked before it is used

                // Owner's fifth review (16 Nov 2026), decision 396: no chmod 0777; the folders must already be writable by the site's PHP user.
                foreach (array('Controller', 'Model', 'View') as $m9d) if (!is_writable(APP . $m9d)) throw new M9BuilderAbort('folder_not_writable ' . $m9d);

                if($result['error'] == 1){
                    echo "Something went wrong. Please try again";
                }else{
                    $result = json_decode($result['response']['finalResult'],true);
                    if($result['controller']){
                        $controller_file_name = Inflector::pluralize(Inflector::Classify($table_name)) . 'Controller.php';
                        $folder = APP . 'Controller';
                        $file = $folder . DS . $controller_file_name;
                        $this->_write_to_file($folder,$file,$result['controller']);
                    }

                    if($result['model']){

                        $name = Inflector::Classify($table_name) . '.php';
                        $folder = APP . 'Model';
                        $file = $folder . DS . $name;
                        $this->_write_to_file($folder,$file,$result['model']);
                    }

                    $viewCode = json_decode($result['viewFile'],true);
                    $folder = APP . 'View' . DS . Inflector::pluralize(Inflector::classify($table_name));        
                    $modelFolder = new Folder();

                    if($result['index']){
                        $file = $folder . DS . 'index.ctp';
                        $this->_write_to_file($folder,$file,$result['index']);
                    }

                    if($result['add']){
                        $file = $folder . DS . 'index.ctp';
                        $this->_write_to_file($folder,$file,$result['index']);
                    }

                    if($result['json']){
                        $file = $folder . DS . 'json.ctp';
                        $this->_write_to_file($folder,$file,$result['json']);
                    }

                    if($result['xml']){
                        $file = $folder . DS . 'xml.ctp';
                        $this->_write_to_file($folder,$file,$result['xml']);
                    }

                    $addCode = json_decode($result['formFile'],true);

                    foreach($addCode as $file => $code){
                        $file = $folder . DS . $file.'.ctp';
                        $this->_write_to_file($folder,$file,$code);
                    }                    

                    if($result['parentModelFile']){

                        $name = Inflector::Classify($customTable['CustomTable']['table_name']) . '.php';
                        $folder = APP . 'Model';
                        $file = $folder . DS . $name;
                        $this->_write_to_file($folder,$file,$result['parentModelFile']);
                    }                    
                }

                $this->CustomTable->create();
                $this->CustomTable->save($customTable);

                $sqlresult = $this->_add_new_table($customTable['CustomTable']['table_name'],$defaultfield,$sqld,$this->request->data['CustomTableFields']);
                $this->_m9_approval_begin();
                if ($this->_show_approvals() && !$this->_save_approvals($this->CustomTable->id)) throw new M9BuilderAbort('approval_save_failed');
                $this->_m9_tx_commit(); // decision 392
                $this->_m9_approval_commit(); // mail leaves only after the form commit succeeded
                $this->redirect(array('action' => 'recreate_child',$this->request->params['pass'][0]));
            } else {
                $this->Session->setFlash(__('The custom table could not be saved. Please, try again.'));
            }
        }
        $customTable = $this->CustomTable->find('first', array('recursive' => 0, 'conditions' => array('CustomTable.id' => $id)));
        $this->set('customTable', $customTable);
        $this->request->data = $customTable;
        
        if ($this->request->params['named']['qc_document_id'] || $this->request->params['named']['process_id']) {            
            // generate table name
            // for documets
            if($this->request->params['named']['qc_document_id'] != ''){
                $qcDocument = $this->CustomTable->QcDocument->find('first', array('recursive' => 0, 'conditions' => array('QcDocument.id' => $this->request->params['named']['qc_document_id']),));
                $this->set('qcDocument', $qcDocument);
            // for processes                
            }else if($this->request->params['named']['process_id'] != ''){
                $process = $this->CustomTable->Process->find('first', array('recursive' => 0, 'conditions' => array('Process.id' => $this->request->params['named']['process_id']),));
                $this->set('process', $process);                
            }else{
                
            }            
        } else {
            // $this->Session->setFlash(__('Chose document first to create table. Tables are linked with documents and document must be present before adding table.'));
            // $this->redirect(array('controller' => 'qc_documents', 'action' => 'index'));
        }

        $fields = json_decode($customTable['CustomTable']['fields'],true);
        foreach($fields as $field){
            $fieldDetails[] = $field;
        }
        $this->set('linkedTosWithDisplay',$this->_returnDetaultField($fields));
        $qcDocument = $this->CustomTable->QcDocument->find('first', array('recursive' => 0, 'conditions' => array('QcDocument.id' => $customTable['CustomTable']['qc_document_id']),));
        $this->set('qcDocument', $qcDocument);
        if ($this->_show_approvals()) {
            $this->loadModel('User');
            $this->User->recursive = 0;
            $userids = $this->User->find('list', array('order' => array('User.name' => 'ASC'), 'conditions' => array('User.publish' => 1, 'User.soft_delete' => 0, 'User.is_approver' => 1)));
            $this->set(array('userids' => $userids));
            $this->set(array('showApprovals' => $this->_show_approvals()));
        }

        $key = $this->_generate_onlyoffice_key($customTable['CustomTable']['id'] . date('Ymdhis'));
        $this->set('filekey', $key);
        $this->_commons($this->Session->read('User.id'));
    }
    
    public function save_rec_doc() {
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
            $file = $this->File->find('first',array('conditions'=>array('File.id'=>$local['record_id'])));
            if($file){            
                $file_type = $file['File']['file_type'];
                $file_name = $file['File']['name'];
                
                $file_name = $this->_clean_table_names($file_name);
                $file_name = $file_name . '.' . $file_type;

                $file_for_save = $path_for_save . DS . $file_name;
                $testfolder = new Folder($path_for_save,true, 0755);
                $testfolder->create($path_for_save);                
                
                try{
                    $this->_m9_mode($path_for_save);    
                }catch (Exception $e){
                    
                }

                try{
                    $this->_m9_mode($file_for_save);
                }catch (Exception $e){
                    
                }
                $downloadUri = $data["url"];

                if (file_get_contents($downloadUri) === FALSE) {
                    
                } else {
                    $new_data = file_get_contents($downloadUri);
                    if (file_put_contents($file_for_save, $new_data)) {
                        $key = $this->_generate_onlyoffice_key($record_id . date('Ymdhis'));

                        $updates = json_decode($file['File']['version_keys'],true);
                        if(is_array($updates)){
                            $last_modified = date('Y-m-d H:i:s');
                            $updates[] = array('version_key'=>$key,'modified'=>$last_modified,'by'=>$local['user']);
                        }else{
                            $last_modified = date('Y-m-d H:i:s');
                            $updates[] = array('version_key'=>$key,'modified'=>$last_modified,'by'=>$local['user']);
                        }


                        $preFileName = 'prev' . '.'.$file_type;
                        $url = $preFile_for_save = $history_path_for_save . DS . $preFileName;
                        $url = str_replace('\/','/',$url);

                        $newHistory = array(                            
                            'key'=>$data['key'],
                            'version'=>count($updates),
                            'changes'=> $data['history']['changes'],
                            'serverVersion'=>$data['history']['serverVersion'],
                            'created'=>$data['history']['changes'][0]['created'],
                            'user'=>array(
                                'id'=>$data['history']['changes'][0]['user']['id'],
                                'name'=>$data['history']['changes'][0]['user']['name'],
                                'url'=>$history_path_for_save = $file_for_save.'-hist' . DS . count($updates) . DS . $preFileName
                            ),
                        );

                        $versions = json_decode($file['File']['versions'],true);
                        $versions[] = $newHistory;

                        $file['File']['version_keys'] = json_encode($updates);
                        $file['File']['versions'] = json_encode($versions);
                        $file['File']['data_received'] = 'from save_doc custom_tables';
                        $file['File']['file_key'] = $key;
                        $file['File']['file_status'] = 1;  
                        $file['File']['last_saved'] = $last_modified;
                        $this->File->create();
                        $this->File->save($file,false);

                        // add -hist folder
                        $history_path_for_save = $file_for_save.'-hist' . DS . count($updates);
                        $historyFolder = new Folder($history_path_for_save);
                        $historyFolder->create($history_path_for_save);                        

                        // save pervious version
                        $downloadUri = $data["url"];

                        // adding diff.zip file
                        $changesData = file_get_contents($data["changesurl"]);
                        file_put_contents($history_path_for_save. DS . "diff.zip", $changesData, LOCK_EX);

                        $fromFile = new File($file_for_save);

                        $new_data = file_get_contents($downloadUri);
                        if (file_put_contents($preFile_for_save, $new_data,LOCK_EX)) {
                            
                        }else{
                            
                        }

                    } else {
                        
                    }                    
                }                
            }
            
        }
        echo "{\"error\":0}";
    }
    public function save_custom_table_doc() {
        $this->autoRender = false;
        $local = $this->request->params['named'];
        
        if (($body_stream = file_get_contents("php://input")) === FALSE) {
            echo "Bad Request";
        }
        $data = json_decode($body_stream, TRUE);
        
        if ($data["status"] == 2) {
            $data = json_decode($body_stream, TRUE);
            $record_id = $this->request->params['named']['record_id'];
            $path_for_save = WWW_ROOT . 'files' . DS . $local['company_id'] . DS . 'custom_tables' . DS . $local['record_id'];
            
            // $qcdoc = $this->CustomTable->QcDocument->find('first', array('recursive' => - 1, 'conditions' => array('QcDocument.id' => $this->request->params['named']['record_id'])));
            $this->loadModel('CustomTable');
            $customTable = $this->CustomTable->find('first',array('conditions'=>array('CustomTable.id'=>$local['record_id'])));
            if($customTable){

                $file_type = $customTable['QcDocument']['file_type'];
                $file_name = $customTable['QcDocument']['title'];
                $document_number = $customTable['QcDocument']['document_number'];
                $document_version = $customTable['QcDocument']['revision_number'];

                // $fileName = $document_number.'-'.$file_name.'-'.$document_version.'.'.$file_type;

                $file_name = $document_number . '-' . $file_name . '-' . $document_version;
                $file_name = $this->_clean_table_names($file_name);
                $fileName = $file_name . '.' . $file_type;

                $file_for_save = $path_for_save . DS . $fileName;
                $testfolder = new Folder($path_for_save);
                $testfolder->create($path_for_save);
                $this->_m9_mode($path_for_save);
                $this->_m9_mode($file_for_save);
                $downloadUri = $data["url"];

                if (file_get_contents($downloadUri) === FALSE) {
                    
                } else {
                    $new_data = file_get_contents($downloadUri);
                    if (file_put_contents($file_for_save, $new_data)) {
                        
                        $key = $this->_generate_onlyoffice_key($record_id . date('Ymdhis'));

                        $updates = json_decode($customTable['CustomTable']['version_keys'],true);
                        if(is_array($updates)){
                            $last_modified = date('Y-m-d H:i:s');
                            $updates[] = array('version_key'=>$key,'modified'=>$last_modified,'by'=>$local['user']);
                        }else{
                            $last_modified = date('Y-m-d H:i:s');
                            $updates[] = array('version_key'=>$key,'modified'=>$last_modified,'by'=>$local['user']);
                        }

                        $customTable['CustomTable']['version_keys'] = json_encode($updates);

                        $customTable['CustomTable']['file_key'] = $key;
                        $customTable['CustomTable']['file_status'] = 1;  
                        $customTable['CustomTable']['last_saved'] = $last_modified;                        
                        $this->CustomTable->create();
                        $this->CustomTable->save($customTable,false);

                    } else {
                        
                    }                    
                }
                
            }            
        }
        echo "{\"error\":0}";
    }

    public function unlock($id = null) {
        if ($this->request->is('post') || $this->request->is('put')) {
            if (empty($this->request->data['CustomTable']['password'])) {
                $this->Session->setFlash(__('Enter password'));
                $this->redirect(array('action' => 'index'));
            } else {
                $customTable = $this->CustomTable->find('first', array('conditions' => array('CustomTable.password' => trim(Security::hash($this->data['CustomTable']['password'], 'md5', true)), 'CustomTable.id' => $this->data['CustomTable']['id'])));
                if ($customTable) {
                    $this->CustomTable->read(null, $this->data['CustomTable']['id']);
                    $this->CustomTable->set('table_locked', 1);
                    $this->CustomTable->save();
                    $this->Session->setFlash(__('Table unlocked'));
                    if($this->data['CustomTable']['next_action'] == 'recreate'){
                        $this->redirect(array('action' => 'recreate',$this->data['CustomTable']['id']));    
                    }else{
                        $this->redirect(array('action' => 'view',$this->data['CustomTable']['id']));
                    }
                    
                } else {
                    $this->Session->setFlash(__('Incorrect Password'));
                    $this->redirect(array('action' => 'index'));
                }
            }
        } else {
            $customTable = $this->CustomTable->find('first', array('conditions' => array('CustomTable.id' => $id)));
            if ($customTable) {
            }
        }
    }

    public function lock($id = null) {
        if ($this->request->is('post') || $this->request->is('put')) {
            if (empty($this->request->data['CustomTable']['password'])) {
                $this->Session->setFlash(__('Enter password'));
                $this->redirect(array('action' => 'index'));
            } else {
                $customTable = $this->CustomTable->find('first', array('conditions' => array('CustomTable.password' => trim(Security::hash($this->data['CustomTable']['password'], 'md5', true)), 'CustomTable.id' => $this->data['CustomTable']['id'])));
                if ($customTable) {
                    $this->CustomTable->read(null, $this->data['CustomTable']['id']);
                    $this->CustomTable->set('table_locked', 0);
                    $this->CustomTable->save();
                    $this->Session->setFlash(__('Table locked'));
                    $this->redirect(array('action' => 'view',$this->data['CustomTable']['id']));
                } else {
                    $this->Session->setFlash(__('Incorrect Password'));
                    $this->redirect(array('action' => 'view',$this->data['CustomTable']['id']));
                }
            }
        } else {
            $customTable = $this->CustomTable->find('first', array('conditions' => array('CustomTable.id' => $id)));
            if ($customTable) {
            }
        }
    }

    public function publish($id = null) {
        if($this->Session->read('User.is_mr') == false){
            $this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
            $this->redirect(array('controller' => 'users', 'action' => 'access_denied',$n));
        }

        if ($this->request->is('post') || $this->request->is('put')) {
            if (empty($this->request->data['CustomTable']['password'])) {
                $this->Session->setFlash(__('Enter password'));
                $this->redirect(array('action' => 'index'));
            } else {
                $customTable = $this->CustomTable->find('first', array('conditions' => array('CustomTable.password' => trim(Security::hash($this->data['CustomTable']['password'], 'md5', true)), 'CustomTable.id' => $this->data['CustomTable']['id'])));
                if ($customTable) {
                    $this->CustomTable->read(null, $this->data['CustomTable']['id']);
                    $this->CustomTable->set('publish', 1);
                    $this->CustomTable->save();
                    $this->Session->setFlash(__('Table published'));
                    $this->redirect(array('action' => 'view',$this->data['CustomTable']['id']));
                } else {
                    $this->Session->setFlash(__('Incorrect Password'));
                    $this->redirect(array('action' => 'view',$this->data['CustomTable']['id']));
                }
            }
        } else {
            $customTable = $this->CustomTable->find('first', array('conditions' => array('CustomTable.id' => $id)));
            if ($customTable) {
            }
        }
    }

    public function hold($id = null) {
        if($this->Session->read('User.is_mr') == false){
            $this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
            $this->redirect(array('controller' => 'users', 'action' => 'access_denied',$n));
        }

        if ($this->request->is('post') || $this->request->is('put')) {
            if (empty($this->request->data['CustomTable']['password'])) {
                $this->Session->setFlash(__('Enter password'));
                $this->redirect(array('action' => 'index'));
            } else {
                $customTable = $this->CustomTable->find('first', array('conditions' => array('CustomTable.password' => trim(Security::hash($this->data['CustomTable']['password'], 'md5', true)), 'CustomTable.id' => $this->data['CustomTable']['id'])));
                if ($customTable) {
                    $this->CustomTable->read(null, $this->data['CustomTable']['id']);
                    $this->CustomTable->set('publish', 0);
                    $this->CustomTable->save();
                    $this->Session->setFlash(__('Table unpublished'));
                    $this->redirect(array('action' => 'view',$this->data['CustomTable']['id']));
                } else {
                    $this->Session->setFlash(__('Incorrect Password'));
                    $this->redirect(array('action' => 'view',$this->data['CustomTable']['id']));
                }
            }
        } else {
            $customTable = $this->CustomTable->find('first', array('conditions' => array('CustomTable.id' => $id)));
            if ($customTable) {
            }
        }
    }

    public function delete_child($id = null) {
        if($this->Session->read('User.is_mr') == false){
            $this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
            $this->redirect(array('controller' => 'users', 'action' => 'access_denied',$n));
        }

        if ($this->request->is('post') || $this->request->is('put')) {
            if (empty($this->request->data['CustomTable']['password'])) {
                $this->Session->setFlash(__('Enter password'));
                $this->redirect(array('action' => 'index'));
            } else {
                $customTable = $this->CustomTable->find('first', array('conditions' => array('CustomTable.password' => trim(Security::hash($this->data['CustomTable']['password'], 'md5', true)), 'CustomTable.id' => $this->data['CustomTable']['id'])));
                if ($customTable) {
                    if(isset($customTable['ParentTable']['has_many']) && is_array($customTable['ParentTable']['has_many'])){
                        $hasManies = array_unique(json_decode($customTable['ParentTable']['has_many'], true), SORT_REGULAR);
                        
                        foreach ($hasManies as $hasMany) {
                            if ($hasMany['table_name'] != $customTable['CustomTable']['table_name']) $newHasMany[] = $hasMany;
                        }
                    }
                    
                    // first remove hasMany from parent table
                    $this->CustomTable->read(null, $customTable['CustomTable']['custom_table_id']);
                    $this->CustomTable->set('has_many', json_encode($newHasMany));
                    $this->CustomTable->save();
                    
                    $parentTable = $this->CustomTable->find('first', array('recursive' => - 1, 'conditions' => array('CustomTable.id' => $customTable['CustomTable']['custom_table_id'])));
                    
                    $this->CreateChildModel->_create_model($parentTable, $parentTable['CustomTable']['table_name'], $parentTable['CustomTable']['name'], $parentTable['CustomTable']['default_field'], $newHasMany, null);
                    if ($customTable['CustomTable']['table_locked'] == 0) {
                        $this->Session->setFlash(__('Can not recreate/delete locked table'));
                        $this->redirect(array('action' => 'index'));
                    }
                    if ($customTable['CustomTable']['name']) {
                        // delete files
                        $folder = APP . 'Controller';
                        $name = Inflector::pluralize(Inflector::Classify($customTable['CustomTable']['table_name'])) . 'Controller.php';
                        if ($name) {
                            unlink($folder . DS . $name);
                        }
                        $folder = APP . 'Model';
                        $name = Inflector::singularize(Inflector::Classify($customTable['CustomTable']['table_name'])) . '.php';
                        if ($name) {
                            unlink($folder . DS . $name);
                        }
                        $folder = APP . 'View';
                        $name = Inflector::pluralize(Inflector::Classify($customTable['CustomTable']['table_name']));
                        $folder = APP . 'View' . DS . Inflector::pluralize(Inflector::classify($customTable['CustomTable']['table_name']));
                        if ($folder != APP . 'View') {
                            $modelFolder = new Folder();
                            $modelFolder->delete($folder);
                            $this->CustomTable->delete(array('CustomTable.id' => $id));
                        }
                        // delete all folders in files folder
                        $filesFolder = new Folder();
                        $path = WWW_ROOT .  'files' . DS . $this->Session->read('User.company_id') . DS . $customTable['CustomTable']['id'];
                        $filesFolder->delete($path);
                        $filesFolder = new Folder();
                        $path = WWW_ROOT .  'files' . DS . $this->Session->read('User.company_id') . DS . $customTable['CustomTable']['table_name'];
                        $filesFolder->delete($path);
                        // delete all records from files table
                        $this->loadModel('File');
                        $this->File->delete(array('File.controller' => $customTable['CustomTable']['table_name']));
                        $sql = 'DROP TABLE IF EXISTS  `' . $customTable['CustomTable']['table_name'] . '`';
                        
                        $q = $this->_m9_ddl_query($sql);
                        $this->CustomTable->delete($customTable['CustomTable']['id']);
                        $this->Session->setFlash(__('Table Deleted'));
                        $this->redirect(array('action' => 'index'));
                    }
                } else {
                    $this->Session->setFlash(__('Incorrect Password'));
                    $this->redirect(array('action' => 'delete', $this->data['CustomTable']['id']));
                }
            }
        }        
    }

    public function update_field() {
        if($this->Session->read('User.is_mr') == false){
            $this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
            $this->redirect(array('controller' => 'users', 'action' => 'access_denied',$n));
        }

        if ($this->data['CustomTableFields'][0]) {
            $this->_commons($this->Session->read('User.id'));
            $customTable = $this->CustomTable->find('first', array('conditions' => array(
                
                'CustomTable.id' => $this->request->params['named']['custom_table_id'])));
            if (!$customTable) {
                $this->Session->setFlash(__('Incorrect Password'));
                $this->redirect(array('action' => 'recreate', $this->request->params['named']['custom_table_id']));
            }
            $preFields = json_decode($this->data['CustomTableFields'][0]['pre_fields'], true);
            
            $fieldTypes = $this->CustomTable->customArray['fieldTypes'];
            if ($this->data['CustomTableFields'][0]['mandetory'] == 1) $n = " NULL";
            else if ($this->data['CustomTableFields'][0]['mandetory'] == 0) $n = " NOT NULL";
            $friendlyName = $customTable['CustomTable']['name'];
            $hasMany = $customTable['CustomTable']['has_many'];
            $table_name = $customTable['CustomTable']['table_name'];
            $defaultfield = $defaultfield;
            if ($preFields['field_type'] == 0 or $preFields['field_type'] == 2 or $preFields['field_type'] == 3 or $preFields['field_type'] == 4) {
                $sql = "ALTER TABLE `" . $table_name . "` CHANGE `" . $preFields['field_name'] . "` `" . $this->data['CustomTableFields'][0]['field_name'] . "` " . $fieldTypes[$preFields['field_type']] . "(" . $this->data['CustomTableFields'][0]['length'] . ")" . $n;
            } else {
                $sql = "ALTER TABLE `" . $table_name . "` CHANGE `" . $preFields['field_name'] . "` `" . $this->data['CustomTableFields'][0]['field_name'] . "` " . $fieldTypes[$preFields['field_type']] . " " . $n;
            }
            $existingFields = json_decode($customTable['CustomTable']['fields'], true);
            $x = 0;
            $newFields = array();
            foreach ($existingFields as $existingField) {
                if ($existingField['field_name'] == $this->request->params['named']['field']) {
                    // change this field
                    $newFields[$x] = $existingField;
                    $newFields[$x]['field_name'] = $this->data['CustomTableFields'][0]['field_name'];
                    $newFields[$x]['length'] = $this->data['CustomTableFields'][0]['length'];
                    $newFields[$x]['size'] = $this->data['CustomTableFields'][0]['size'];
                    $newFields[$x]['mandetory'] = $this->data['CustomTableFields'][0]['mandetory'];
                    $newFields[$x]['csvoptions'] = $this->data['CustomTableFields'][0]['csvoptions'];
                } else {
                    $newFields[$x] = $existingField;
                }
                $x++;
            }
            // save json and then update views and model and controller
            $customTable['CustomTable']['fields'] = json_encode($newFields);
            $this->CustomTable->create();
            $this->CustomTable->save($customTable, true);
            if ($customTable['CustomTable']['default_field'] == 0) {
                $defaultfield = 'name';
                foreach ($newFields as $chkd) {
                    if (in_array('name', $chkarray)) {
                        $this->Session->setFlash(__('There are duplicate fields'));
                        $this->redirect(array('action' => 'add', $id, 'qc_document_id' => $this->request->params['named']['qc_document_id']));
                    }
                }
            }
            if ($customTable['CustomTable']['default_field'] == 1) {
                $defaultfield = 'title';
                foreach ($newFields as $chkd) {
                    if (in_array('title', $chkarray)) {
                        $this->Session->setFlash(__('There are duplicate fields'));
                        $this->redirect(array('action' => 'add', $id, 'qc_document_id' => $this->request->params['named']['qc_document_id']));
                    }
                }
            }

            if ($customTable['CustomTable']['default_field'] == 2) {
                $defaultfield = $customTable['CustomTable']['default_field_other'];
                $defaultfield = $this->_clean_table_names($defaultfield);
                
                foreach ($newFields as $chkd) {
                    if (in_array($defaultfield, $chkarray)) {
                        $this->Session->setFlash(__('There are duplicate fields'));
                        $this->redirect(array('action' => 'recreate', $id, 'qc_document_id' => $this->request->params['named']['qc_document_id']));
                    }
                }                
            }

            $customTable['CustomTableFields'] = $newFields;

            $this->_m9_ddl_query($sql);
            $this->Session->setFlash(__('Changes Saved.'));
            $this->redirect(array('action' => 'recreate', $customTable['CustomTable']['id']));
        } else {
            $this->set('existingField', $this->data);
            $this->_commons($this->Session->read('User.id'));
        }
    }

    public function update_child_field() {
        if($this->Session->read('User.is_mr') == false){
            $this->Session->setFlash(__('You are not authorized to view this section'), 'default', array('class' => 'alert alert-danger'));
            $this->redirect(array('controller' => 'users', 'action' => 'access_denied',$n));
        }
        
        if ($this->data['CustomTableFields'][0]) {
            $this->_commons($this->Session->read('User.id'));
            $customTable = $this->CustomTable->find('first', array('conditions' => array('CustomTable.password' => trim(Security::hash($this->data['CustomTableFields'][0]['password'], 'md5', true)), 'CustomTable.id' => $this->request->params['named']['custom_table_id'])));
            if (!$customTable) {
                $this->Session->setFlash(__('Incorrect Password'));
                $this->redirect(array('action' => 'recreate_child', $this->request->params['named']['custom_table_id']));
            }
            $preFields = json_decode($this->data['CustomTableFields'][0]['pre_fields'], true);
            
            $fieldTypes = $this->CustomTable->customArray['fieldTypes'];
            if ($this->data['CustomTableFields'][0]['mandetory'] == 1) $n = " NULL";
            else if ($this->data['CustomTableFields'][0]['mandetory'] == 0) $n = " NOT NULL";
            $friendlyName = $customTable['CustomTable']['name'];
            $hasMany = $customTable['CustomTable']['has_many'];
            $table_name = $customTable['CustomTable']['table_name'];
            $defaultfield = $defaultfield;
            if ($preFields['field_type'] == 0 or $preFields['field_type'] == 2 or $preFields['field_type'] == 3 or $preFields['field_type'] == 4) {
                $sql = "ALTER TABLE `" . $table_name . "` CHANGE `" . $preFields['field_name'] . "` `" . $this->data['CustomTableFields'][0]['field_name'] . "` " . $fieldTypes[$preFields['field_type']] . "(" . $this->data['CustomTableFields'][0]['length'] . ")" . $n;
            } else {
                $sql = "ALTER TABLE `" . $table_name . "` CHANGE `" . $preFields['field_name'] . "` `" . $this->data['CustomTableFields'][0]['field_name'] . "` " . $fieldTypes[$preFields['field_type']] . " " . $n;
            }
            $existingFields = json_decode($customTable['CustomTable']['fields'], true);
            $x = 0;
            $newFields = array();
            foreach ($existingFields as $existingField) {
                if ($existingField['field_name'] == $this->request->params['named']['field']) {
                    // change this field
                    $newFields[$x] = $existingField;
                    $newFields[$x]['field_name'] = $this->data['CustomTableFields'][0]['field_name'];
                    $newFields[$x]['length'] = $this->data['CustomTableFields'][0]['length'];
                    $newFields[$x]['size'] = $this->data['CustomTableFields'][0]['size'];
                    $newFields[$x]['mandetory'] = $this->data['CustomTableFields'][0]['mandetory'];
                    $newFields[$x]['csvoptions'] = $this->data['CustomTableFields'][0]['csvoptions'];
                } else {
                    $newFields[$x] = $existingField;
                }
                $x++;
            }
            // save json and then update views and model and controller
            $customTable['CustomTable']['fields'] = json_encode($newFields);
            $this->CustomTable->create();
            $this->CustomTable->save($customTable, true);
            if ($customTable['CustomTable']['default_field'] == 0) {
                $defaultfield = 'name';
                foreach ($newFields as $chkd) {
                    if (in_array('name', $chkarray)) {
                        $this->Session->setFlash(__('There are duplicate fields'));
                        $this->redirect(array('action' => 'add', $id, 'qc_document_id' => $this->request->params['named']['qc_document_id']));
                    }
                }
            }
            if ($customTable['CustomTable']['default_field'] == 1) {
                $defaultfield = 'title';
                foreach ($newFields as $chkd) {
                    if (in_array('title', $chkarray)) {
                        $this->Session->setFlash(__('There are duplicate fields'));
                        $this->redirect(array('action' => 'add', $id, 'qc_document_id' => $this->request->params['named']['qc_document_id']));
                    }
                }
            }

            if ($customTable['CustomTable']['default_field'] == 2) {
                $defaultfield = $customTable['CustomTable']['default_field_other'];
                $defaultfield = $this->_clean_table_names($defaultfield);
                
                foreach ($newFields as $chkd) {
                    if (in_array($defaultfield, $chkarray)) {
                        $this->Session->setFlash(__('There are duplicate fields'));
                        $this->redirect(array('action' => 'recreate', $id, 'qc_document_id' => $this->request->params['named']['qc_document_id']));
                    }
                }                
            }

            $customTable['CustomTableFields'] = $newFields;
            
            $this->CreateChildController->_create_controller($this->request->data, $table_name, $friendlyName);
            // this should only update haMany
            // $hasMany = $table_name_version;
            $this->CreateChildModel->_create_model($customTable, $customTable['CustomTable']['table_name'], $customTable['CustomTable']['name'], $defaultfield, $hasMany, $hasManyExisting);
            
            $this->CreateChildForm->_create_form($customTable, $customTable['CustomTable']['table_name'], $customTable['CustomTable']['name'], $defaultfield, null, null);
            $this->CreateChildView->_create_view($this->request->data, $this->request->data['CustomTable']['table_name'], $this->request->data['CustomTable']['name'], $defaultfield, null, null);
            $this->_m9_ddl_query($sql);
            $this->Session->setFlash(__('Changes Saved.'));
            $this->redirect(array('action' => 'recreate_child', $customTable['CustomTable']['id']));
        } else {
            $this->set('existingField', $this->data);
            $this->_commons($this->Session->read('User.id'));
        }
    }

    public function reports() {
        $this->render('/Elements/reports');
        $this->_commons($this->Session->read('User.id'));
    }

    public function last_updated_record($table_name = null, $user_id = null,$option = null,$schedule_id = null) {
        $schedules = $this->CustomTable->QcDocument->Schedule->find('list');
        $model = Inflector::classify($table_name);
        $scheduleCondition = array();
        $branchConditionModel = array();
        $branchCondition = array();
        $branchConditionModel = array();
        $scheduleConditionModel = array();

        if(!$this->Session->read('User.is_mr')){
            if($this->Session->read('User.is_view_all') == false){
                $branchCondition = array('OR'=>array('History.branchid'=>json_decode($this->Session->read('User.assigned_branches'),true)));
                $branchConditionModel = array($model.'.branchid'=>json_decode($this->Session->read('User.assigned_branches'),true));
            }
        }else{
            $branchCondition = array();
            $branchConditionModel = array();
        }

        // for schedule
        // add schedule conditions using switch case as $schedulecondition

        // for data entry type 
        // add 1-2-3 type conditions
        // 0=>'Any user should update a single document for a defined schedule',
        // just use schedule condition

        // 1=>'Every user should update a saperate document for a defined schedule',
        // use schedule condition along with user condition

        // 2=>'Multiple users should update a single document for a defined schedule',
        // Check if current user has added any record
        // if not, check if any other user has added any record


        // 0=>'Any user should update a single document for a defined schedule',
        // 1=>'Every user should update a saperate document for a defined schedule',
        // 2=>'Multiple users should update a single document for a defined schedule',

        try{
            $this->loadModel($model);

            switch ($schedules[$schedule_id]){
            case 'Daily':            
                $scheduleCondition = array('DATE(History.created)' =>  date('Y-m-d'));
                $scheduleConditionModel = array('DATE('.$model.'.created)' =>  date('Y-m-d'));
            break;

            case 'Weekly':
                $scheduleCondition = array('WEEK(History.created)' =>  date('W'),'YEAR(History.created)' =>  date('Y'));
                $scheduleConditionModel = array('WEEK('.$model.'.created)' =>  date('W'),'YEAR('.$model.'.created)' =>  date('Y'));
            break;
            
            case 'Monthly':
                $scheduleCondition = array('MONTH(History.created)' =>  date('m'),'YEAR(History.created)' =>  date('Y'));
                $scheduleConditionModel = array('MONTH('.$model.'.created)' =>  date('m'),'YEAR('.$model.'.created)' =>  date('Y'));
            break;
            case 'Quarterly':
                $scheduleCondition = array('QUARTER(History.created)' =>  ceil(date('m',time())/3),'YEAR(History.created)' =>  date('Y'));
                $scheduleConditionModel = array('QUARTER('.$model.'.created)' =>  ceil(date('m',time())/3),'YEAR('.$model.'.created)' =>  date('Y'));
            break;

            case 'Yearly':
                $scheduleCondition = array('YEAR(History.created)' =>  date('Y'));
                $scheduleConditionModel = array('YEAR('.$model.'.created)' =>  date('Y'));
            break;

            case 'Half-Yearly':
                $scheduleCondition = array('MONTH(History.created)' =>  ceil(date('m',time())/2),'YEAR(History.created)' =>  date('Y'));
                $scheduleConditionModel = array('MONTH('.$model.'.created)' =>  ceil(date('m',time())/2),'YEAR('.$model.'.created)' =>  date('Y'));
            break;

            case 'none':

            break;
        }


        switch ($option) {
            case 0: // Any user should update a single document for a defined schedule'
            // $condition = array_merge($scheduleCondition, array($model . '.created_by' => $this->Session->read('User.id')));
            $condition = array_merge($scheduleCondition,$branchCondition);
            $conditionModel = array_merge($scheduleConditionModel,$branchConditionModel);
            break;
            case 1: // Every user should update a saperate document for a defined schedule            
            $condition = array_merge($scheduleCondition, $branchCondition, array('History.created_by' => $this->Session->read('User.id')));
            $conditionModel = array_merge($scheduleConditionModel,$branchConditionModel, array($model . '.created_by' => $this->Session->read('User.id')));
            break;
            case 2: // Multiple users should update a single document for a defined schedule                    
            // $condition = array( 'OR'=>array( $scheduleCondition, array($model . '.created_by' => $this->Session->read('User.id'))));
            $condition = array_merge($scheduleCondition,$branchCondition);
            $conditionModel = array_merge($scheduleConditionModel,$branchConditionModel);
            break;
        }

            if($option == 2){
                $this->loadModel('History');
                $history = $this->History->find('first',array(
                    'fields'=>array(
                        'History.id',
                        'History.sr_no',
                        'History.model_name',
                        'History.created_by',
                        'History.record_id',
                        'History.action',
                        'History.created',
                        'History.post_values'
                    ),
                    'recursive'=>-1,
                    'conditions'=>array(
                        $condition,
                        'History.model_name'=>$model,
                        'History.created_by'=>$this->Session->read('User.id'),
                        'OR'=>array('History.action'=>array('add','edit')),
                        'History.post_values != '=>'[[]]'
                    ),
                    'order'=>array('History.created'=>'DESC')
                ));

                if(!$history){
                    $this->loadModel('History');
                    $history = $this->History->find('first',array(
                        'fields'=>array(
                            'History.id',
                            'History.sr_no',
                            'History.model_name',
                            'History.created_by',
                            'History.record_id',
                            'History.action',
                            'History.created',
                            'History.post_values'
                        ),
                        'recursive'=>-1,
                        'conditions'=>array(
                            $condition,
                            'History.model_name'=>$model,
                            // 'History.created_by'=>$this->Session->read('User.id'),
                            'OR'=>array('History.action'=>array('add','edit')),
                            'History.post_values != '=>'[[]]'
                        ),
                        'order'=>array('History.created'=>'DESC')
                    ));
                }

            }else{
                $this->loadModel('History');
                $history = $this->History->find('first',array(
                    'fields'=>array(
                        'History.id',
                        'History.sr_no',
                        'History.model_name',
                        'History.created_by',
                        'History.record_id',
                        'History.action',
                        'History.created',
                        'History.post_values'
                    ),
                    'recursive'=>-1,
                    'conditions'=>array(
                        $condition,
                        'History.model_name'=>$model,
                        // 'History.created_by'=>$this->Session->read('User.id'),
                        'OR'=>array('History.action'=>array('add','edit')),
                        'History.post_values != '=>'[[]]'
                    ),
                    'order'=>array('History.created'=>'DESC')
                ));
            }
            
            // options
            // shared with all the users
            // shared with branches
            // shared with departments
            
            if($history){

                if($history['History']['record_id']){
                    $last_updated = $this->$model->find('first', array('fields' => array(
                        $model . '.created',
                        $model . '.id',
                        $model . '.created_by',
                    ), 
                        'conditions' => array($model.'.id'=>$history['History']['record_id']), 
                        'order' => array($model . '.created' => 'DESC')
                        )
                    );
                    $last_updated[$model]['updated_by'] = $history['History']['created_by'];
                }else{                    
                    $last_updated = $this->$model->find('first', array('fields' => array(
                        $model . '.created', 
                        $model . '.id',
                        $model . '.created_by',
                    ), 
                        'conditions' => array($conditionModel), 
                        'order' => array($model . '.created' => 'DESC')
                        )
                    );
                    $last_updated[$model]['updated_by'] = $last_updated[$model]['created_by'];
                }                
            }            
            
            
            if ($last_updated) {                
                return $last_updated[$model];
            } else { 
                return false;
            }    
        } catch (Exception $e){

        }        
    }

    public function create_master(){
        if ($this->request->is('post') || $this->request->is('put')) {            
            if (false === $this->_m9_generator_ready()) $this->_m9_generator_refuse(); // owner's fourth review: no change unless the generator answers
            $this->_m9_tx_begin(); // owner's fifth review (decision 392): from here this submit changes everything or nothing
            $this->autoRender = false;
            $this->request->data['CustomTable']['password'] = Security::hash($this->request->data['CustomTable']['password'], 'md5', true);
            $tableName = $this->_make_table_name(null,'masters');
            // check if table already exists
            $checkExisting = $this->CustomTable->find('count',array('conditions'=>array('CustomTable.table_name'=>$tableName[0])));
            if($checkExisting > 0){
                echo "Table already exists";
                return false;
            }
            $table_name = $tableName[0];
            $friendlyName = $this->request->data['CustomTable']['table_name'];

            $this->request->data['CustomTable']['table_name'] = $tableName[0];
            $this->request->data['CustomTable']['table_version'] = $tableName[1];
            $this->request->data['CustomTable']['table_type'] = 2;
            $this->request->data['CustomTable']['display_field'] = 0;
            $this->request->data['CustomTable']['default_field'] = 0;
            $this->request->data['CustomTable']['form_layout'] = 0;
            $this->request->data['CustomTable']['table_locked'] = 0;

            $this->request->data['CustomTable']['fields'] = '[{"dummy":"","field_name":"name","field_label":"Name","old_field_name":"name","linked_to":"-1","display_type":"0","field_type":"0","length":"255","size":"12","data_type":"text","mandatory":"1","default_field":"1","index_show":"1","drop":"0","new":"0","sequence":"","add_disabled":"0","who_can_edit":"\"\"","show_comments":""}]';
            
            $this->CustomTable->create();
            if($this->CustomTable->save($this->request->data,false)){
                
                $customTable = $this->CustomTable->find('first', array('conditions' => array('CustomTable.id' => $this->CustomTable->id)));                
                
                $defaultfield = 'name';
                $data = array($this->request->data,$table_name,$friendlyName,$defaultfield);
                $result = $this->curl('post','custom_forms','create_masters',$data);            
                $result = json_decode($result,true);
                $this->_m9_tx_check_answer($result, 'create_masters'); // decision 392: the whole answer is checked before it is used

                if($result['error'] == 1 || $result == null){
                    echo "Something went wrong. Please try again";
                }else{
                    $this->_clear_cake_cache();
                    Configure::write('debug',2);

                    $result = json_decode($result['response']['finalResult'],true);
                    $viewCode = json_decode($result['controller_model'],true);


                    $folder = APP . 'View' . DS . Inflector::pluralize(Inflector::classify($table_name));        
                    $viewFolder = new Folder();
                    $this->_m9_mode($folder);
                    
                    if($viewCode['controller']){

                        $controller_file_name = Inflector::pluralize(Inflector::Classify($table_name)) . 'Controller.php';
                        $folder = APP . 'Controller';
                        $file = $folder . DS . $controller_file_name;                        
                        $this->_write_to_file($folder,$file,$viewCode['controller']);
                    }

                    if($viewCode['model']){
                        $name = Inflector::Classify($table_name) . '.php';
                        $folder = APP . 'Model';
                        $file = $folder . DS . $name;
                        $this->_write_to_file($folder,$file,$viewCode['model']);
                    }
                    $viewCode = '';
                    
                    $viewCode = json_decode($result['viewFile'],true);

                    if($viewCode['index']){
                        $folder = APP . 'View' . DS . Inflector::pluralize(Inflector::classify($table_name));                         
                        $file = $folder . DS . 'index.ctp';                        
                        $this->_write_to_file($folder,$file,$viewCode['index']);                        
                    }                

                    if($viewCode['view']){    
                        $folder = APP . 'View' . DS . Inflector::pluralize(Inflector::classify($table_name));
                        $file = $folder . DS . 'view.ctp';                        
                        $this->_write_to_file($folder,$file,$viewCode['view']);
                    }

                    $addCode = json_decode($result['formFile'],true);

                    if($addCode['add']){
                        $folder = APP . 'View' . DS . Inflector::pluralize(Inflector::classify($table_name));
                        $file = $folder . DS . 'add.ctp';                        
                        $this->_write_to_file($folder,$file,$addCode['add']);
                        
                    }

                    if($addCode['edit']){
                        $folder = APP . 'View' . DS . Inflector::pluralize(Inflector::classify($table_name));                        
                        $file = $folder . DS . 'edit.ctp';                        
                        $this->_write_to_file($folder,$file,$addCode['edit']);
                        
                    }
                }

                $sql = "CREATE TABLE `" . $customTable['CustomTable']['table_name'] . "` ( ";
                $sql.= "`id` varchar(36) NOT NULL,";
                $sql.= "`sr_no` int(11) NOT NULL AUTO_INCREMENT,";
                $sql.= "`name` varchar(255) NOT NULL,";
                $sql.= "            
                `custom_table_id` varchar(36) DEFAULT NULL,
                `publish` tinyint(1) DEFAULT '1' COMMENT '0=Un 1=Pub',
                `record_status` tinyint(1) DEFAULT '0' COMMENT '0=Un-locked, 1=Locked',
                `status_user_id` varchar(36) DEFAULT NULL,
                `approval_step_id` varchar(36) DEFAULT NULL,
                `created_by` varchar(36) NOT NULL,
                `created` datetime NOT NULL,
                `modified_by` varchar(36) NOT NULL,
                `approved_by` varchar(36) DEFAULT NULL,
                `prepared_by` varchar(36) DEFAULT NULL,
                `modified` datetime NOT NULL,
                `soft_delete` tinyint(1) NOT NULL DEFAULT '0',
                `branchid` varchar(36) DEFAULT NULL,
                `departmentid` varchar(36) DEFAULT NULL,
                `company_id` varchar(36) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `sr_no` (`sr_no`)
                ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
                ";                

                $this->_clear_cake_cache();
                Configure::write('debug',2);

                $this->_m9_tx_queue_create($customTable['CustomTable']['table_name'], $sql);
                $this->_m9_tx_commit(); // decision 392: files, then the table; on failure all is undone
                if (true) {
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
                    $controller_file_name = str_replace('Controller','', $controller_file_name);
                    $controller_file_name = str_replace('.php','', $controller_file_name);
                    $linkedTos[$controller_file_name] = $customTable['CustomTable']['name'] . " ver " . $customTable['CustomTable']['table_version'];

                    asort($linkedTos);                    
                    $this->_clear_cake_cache();
                    Configure::write('debug',0);
                }else{
                    echo 'SQL failed!';
                }
            }else{
                return false;
            }
            exit;
        }
    }    

    public function add_template_fields(){
        $fields = json_decode(base64_decode($this->data['fields']),true);
        $fields = json_decode($fields[0],true);
        $this->set('fields',$fields);
    }

    public function get_data($field_name = null, $custom_table_id = null)
    {
        $customTable = $this->CustomTable->find("first", [
            "conditions" => ["CustomTable.id" => $custom_table_id],
            "recursive" => -1,
        ]);
        
        $model = Inflector::classify($customTable["CustomTable"]["table_name"]);
        
        $this->loadModel($model);
        $belogs = $this->$model->belongsTo;
        $hasMany = $this->$model->hasMany;
        $customArray = $this->$model->customArray;
        

        foreach ($belogs as $b => $vals) {
            if ($vals["foreignKey"] == $field_name) {
            }
        }
        $field = Inflector::pluralize(Inflector::variable($field_name));
        foreach ($customArray as $key => $val) {
            if ($key == $field) {
                $result = $val;
            }
        }

        if ($result) {
            $this->set("result", $result);
        } else {
            $result = [
                0 => "Null/Empty",
                1 => "Not NULl or Empty",
            ];
            $this->set("result", $result);
        }

        $this->set("model", $model);
        $this->set("field_name", $field_name);
    }

    public function reload_document($id = null){
        
    }

    public function check_before_delete($id = null){
        $customTable = $this->CustomTable->find('first',array('conditions'=>array('CustomTable.id'=>$id)));
        $cmodelName = Inflector::Classify($customTable['CustomTable']['table_name']);
        $dataSource = ConnectionManager::getDataSource('default');
        $sources = $dataSource->listSources();
        foreach($sources as $model){
            try{
                $this->loadModel(Inflector::classify($model));
                $m = Inflector::classify($model);
                $s = $this->$m->belongsTo;
                $s2 = $this->$m->schema();
                foreach($s as $pname => $fields){
                    if($fields['className'] == $cmodelName){
                        $found[] = $model;
                    }
                }
            }catch (Exception $e){

            }            
        }
        return $found;
    }

    public function field_details($id = null){
        $table = $this->CustomTable->find('first',array('conditions'=>array('CustomTable.id'=>$id),'recursive'=>-1,'fields'=>array('CustomTable.id','CustomTable.name','CustomTable.table_name', 'CustomTable.fields')));
        $fields = json_decode($table['CustomTable']['fields'],true);

        
        $formAddId = Inflector::singularize(Inflector::Classify($table['CustomTable']['table_name'])).'AddForm';
        $formEditId = Inflector::singularize(Inflector::Classify($table['CustomTable']['table_name'])).'EditForm';
        

        foreach($fields as $field){
            $ids[$field['field_name']]['id'] = Inflector::classify($table['CustomTable']['table_name']).Inflector::classify($field['field_name']);
            $ids[$field['field_name']]['name'] = 'data["'.Inflector::classify($table['CustomTable']['table_name']).'"]["'.$field['field_name'].']';
        }        
        exit;

    }

    public function save_scripts(){        
        $customTable = $this->CustomTable->find('first',array('recursive'=>-1, 'conditions'=>array('CustomTable.id'=>$this->request->data['CustomTable']['id'])));
        if($customTable){
            $customTable['CustomTable']['add_form_script'] = $this->request->data['CustomTable']['add_form_script'];
            $customTable['CustomTable']['edit_form_script'] = $this->request->data['CustomTable']['edit_form_script'];
            $this->CustomTable->create();
            $this->CustomTable->save($customTable,false);
            
            $this->Session->setFlash(__('Scripts Saved'));
            $this->redirect(array('action' => 'view', $this->request->data['CustomTable']['id']));
        }
        exit;
    }

    public function reset_table_password($id = null){
        if($id){
            $customTable = $this->CustomTable->find('first',array('conditions'=>array(
                'CustomTable.created_by'=>$this->Session->read('User.id'),
                'CustomTable.id'=>$id
            ),'recursive'=>-1));
            if($customTable){
                $this->loadModel('User');
                $user = $this->User->find('first',array('recursive'=>-1,'fields'=>array('User.id','User.password'), 'conditions'=>array('User.id'=>$this->Session->read('User.id'))));
                if($user){                    
                    $pass = base64_encode($user['User']['password']);                    
                    $customTable['CustomTable']['password'] = base64_decode($pass);                    
                    $this->CustomTable->create();
                    if($this->CustomTable->save($customTable,false)){
                        $this->Session->setFlash(__('You login password is now password to unlock this table.'));
                        $this->redirect(array('action' => 'unlock', $customTable['CustomTable']['id']));
                    }
                }
            }else{
                $this->Session->setFlash(__('Invalid Credentials.'));
                $this->redirect(array('action' => 'unlock', $id));
            }
        }
    }

    public function loadbelongstos($id = null){
        $customTable = $this->CustomTable->find('first',array('recursive'=>-1,'conditions'=>array('CustomTable.id'=>$id),'fields'=>array('CustomTable.id','CustomTable.belongs_to')));
        if($customTable){
            $this->set('customTable',$customTable);
        }
    }

    public function loadbegonstotablefields($m = null){
        if($m != -1 && $m != null){
            $model = Inflector::Classify($m);
            $this->loadModel($model);
            $skip = array('file_key','versions','version_keys','last_saved','update_custom_table_document','file_type','add_records','date_created','document_type','mark_for_cr_update','temp_date_of_issue','temp_effective_from_date','cr_id','old_cr_id','parent_id','linked_documents','user_id','cover_page','page_orientation','pdf_footer_id','user_session_id','signature');

            $allfields = array_diff(array_keys($this->$model->schema()),$skip);
            // $allfields = array_keys($this->$model->schema());
            $this->set('allfields',$allfields);
            $this->set('belogsToModel',$model);
        }        
    }

    public function add_belongs_to_fields($model = null, $id = null, $f = null,$field = null) {
        $this->set('model',$model);
        $this->set('field',$field);
        $this->set('f',$f);
    }

    public function pdf_templates(){

    }

    public function link_processes($id = null){
        $this->loadModel('CustomTableProcess');
        if ($this->request->is('post')) {
            $processess = $this->request->data['str'];
            if($processess != null && $id != null){
                $this->CustomTableProcess->deleteAll(array('CustomTableProcess.custom_table_id'=>$id));
                foreach($processess as $process){
                    if($process != -1){
                        $data['CustomTableProcess']['custom_table_id'] = $id;
                        $data['CustomTableProcess']['process_id'] = $process;
                        $data['CustomTableProcess']['sequence'] = 1;
                        $this->CustomTableProcess->create();
                        $this->CustomTableProcess->save($data,false);
                    }                    
                }
                $this->redirect(array('action' => 'link_processes', $id, date('Ymdhis')));
            }else{
                echo "error";
            }
        }


        $this->loadModel('Process');
        $processes = $this->Process->find('list');
        $this->set('processes',$processes);
        $this->set('id',$id);
        $linkedProcesses = $this->CustomTableProcess->find('all',array('order'=>array('CustomTableProcess.sequence'=>'ASC'), 'conditions'=>array('CustomTableProcess.custom_table_id'=>$id)));
        $this->set('linkedProcesses',$linkedProcesses);
        return true;
    }

    public function delete_link_processes($id = null, $process_id = null){
        if($id && $process_id){
            $this->loadModel('CustomTableProcess');
            $this->CustomTableProcess->deleteAll(array('CustomTableProcess.custom_table_id'=>$id,'CustomTableProcess.process_id'=>$process_id));
            $this->redirect(array('action' => 'link_processes', $id, date('Ymdhis')));
        }else{
            $this->redirect(array('action' => 'link_processes', $id, date('Ymdhis')));
        }
        
    }

    public function process_sequence($id = null){
        $this->loadModel('CustomTableProcess');
        $seq = 1;
        foreach(json_decode($this->request->params['named']['processes']) as $process_id){
            $process = $this->CustomTableProcess->find('first',array('conditions'=>array(
                'CustomTableProcess.custom_table_id'=>$this->request->params['named']['id'],
                'CustomTableProcess.process_id'=>$process_id,
            ),'recursive'=>-1));

            if($process){
                $this->CustomTableProcess->create();
                $process['CustomTableProcess']['sequence'] = $seq;
                $this->CustomTableProcess->create();
                $this->CustomTableProcess->save($process,false);
                $seq++;
            }            
        }   
        $this->redirect(array('action' => 'link_processes', $this->request->params['named']['id'], date('Ymdhis')));        
    }

    public function updateaccess($id = null){
        if ($this->request->is('post')) {
            if($this->Session->read('User.is_mr') == true){
                $customTable = $this->CustomTable->find('first',array('conditions'=>array('CustomTable.id'=>$id)));
                if($customTable){
                    if($this->request->data['CustomTable']['creators']){
                        $customTable['CustomTable']['creators'] = json_encode($this->request->data['CustomTable']['creators']);
                    }

                    if($this->request->data['CustomTable']['editors']){
                        $customTable['CustomTable']['editors'] = json_encode($this->request->data['CustomTable']['editors']);
                    }

                    if($this->request->data['CustomTable']['viewers']){
                       $customTable['CustomTable']['viewers'] = json_encode($this->request->data['CustomTable']['viewers']);
                    }

                    if($this->request->data['CustomTable']['approvers']){
                       $customTable['CustomTable']['approvers'] = json_encode($this->request->data['CustomTable']['approvers']);
                    }

                    $this->CustomTable->create();
                    $this->CustomTable->save($customTable,false);
                    
                    $this->Session->setFlash(__('Access Updated.'));
                    $this->redirect(array('action' => 'view', $id, date('Ymdhis')));
                }
            }else{
                $this->Session->setFlash(__('Invalid Access.'));
                $this->redirect(array('action' => 'view', $id, date('Ymdhis')));
            }
        }
    }

    public function updatedataentry($qc_document_id = null, $custom_table_id = null){
        if ($this->request->is('post')) {
            $qcDocument = $this->CustomTable->QcDocument->find('first',array('conditions'=>array('QcDocument.id'=>$qc_document_id),'recursive'=>-1,));
            if($qcDocument){
                $this->request->data['QcDocument']['add_records'] = 1;
                $qcDocument['QcDocument']['schedule_id'] = $this->request->data['QcDocument']['schedule_id'];
                $qcDocument['QcDocument']['add_records'] = $this->request->data['QcDocument']['add_records'];
                $qcDocument['QcDocument']['data_type'] = $this->request->data['QcDocument']['data_type'];
                $qcDocument['QcDocument']['data_update_type'] = $this->request->data['QcDocument']['data_update_type'];                
                $this->CustomTable->QcDocument->create();
                if($this->CustomTable->QcDocument->save($qcDocument,false)){
                    $this->Session->setFlash(__('Dataentry Details Updated.'));
                }else{
                    $this->Session->setFlash(__('Dataentry Details Updated failed'));
                }                
                $this->redirect(array('action' => 'view', $custom_table_id, date('Ymdhis')));
            }
        }
        $this->Session->setFlash(__('Incorrect access.'));
        $this->redirect(array('action' => 'view', $custom_table_id, date('Ymdhis')));
    }

    public function update_access($custom_table_id = null, $user_id = null){
        $this->autoRender = false;
        if($this->Session->read('User.is_mr') == true){
            if ($this->request->is('post')) {
                $customTable = $this->CustomTable->find('first',array(
                    'fields'=>array('CustomTable.id','CustomTable.creators','CustomTable.viewers','CustomTable.editors','CustomTable.approvers','CustomTable.qc_document_id'),
                    'recursive'=>-1,
                    'conditions'=>array('CustomTable.id'=>$this->request->data['custom_table_id'])));
                
                if($this->request->data['typ'] == 0){
                    $action = 'remove'.$this->request->data['action'];
                }else{
                    $action = 'add'.$this->request->data['action'];
                }

                if($customTable){
                    switch ($action) {
                        case 'addcreate' :
                            $users = json_decode($customTable['CustomTable']['creators'],true);
                            $users[] = $this->request->data['user_id'];
                            $customTable['CustomTable']['creators'] = json_encode(array_values($users));

                        break;

                        case 'removecreate' :
                            $users = json_decode($customTable['CustomTable']['creators'],true);
                            $users = $this->_removefromarray($users,$this->request->data['user_id']);
                            $customTable['CustomTable']['creators'] = json_encode(array_values($users));

                        break;

                        case 'addedit' :
                            $users = json_decode($customTable['CustomTable']['editors'],true);
                            $users[] = $this->request->data['user_id']; 
                            $customTable['CustomTable']['editors'] = json_encode(array_values($users));
                        break;

                        case 'removeedit' :
                            $users = json_decode($customTable['CustomTable']['editors'],true);
                            $users = $this->_removefromarray($users,$this->request->data['user_id']);
                            $customTable['CustomTable']['editors'] = json_encode(array_values($users));
                        break;

                        case 'addview' :
                            $users = json_decode($customTable['CustomTable']['viewers'],true);
                            $users[] = $this->request->data['user_id'];  
                            $customTable['CustomTable']['viewers'] = json_encode(array_values($users));
                        break;

                        case 'removeview' :
                            $users = json_decode($customTable['CustomTable']['viewers'],true);
                            $users = $this->_removefromarray($users,$this->request->data['user_id']);
                            $customTable['CustomTable']['viewers'] = json_encode(array_values($users));
                        break;

                        case 'addapprove' :
                            $users = json_decode($customTable['CustomTable']['approvers'],true);
                            $users[] = $this->request->data['user_id']; 
                            $customTable['CustomTable']['approvers'] = json_encode(array_values($users)); 
                        break;

                        case 'removeapprove' :
                            $users = json_decode($customTable['CustomTable']['approvers'],true);
                            $users = $this->_removefromarray($users,$this->request->data['user_id']);
                            $customTable['CustomTable']['approvers'] = json_encode(array_values($users));
                        break;
                    }
                    
                    $this->CustomTable->create();
                    $this->CustomTable->save($customTable,false);

                    // remove copy_acl_from
                    $this->loadModel('User');
                    $user = $this->User->find('first',array('recursive'=>-1,'conditions'=>array('User.id'=>$this->request->data['user_id'])));
                    if($user){
                        $user['User']['copy_acl_from'] = NULL;
                        $this->User->create();
                        $this->User->save($user,false);
                    }
                    // update qc document permission
                    // if add/ edit/ approver is added then also add VIEW permission to QC Document
                    if($this->request->data['typ'] == 1){
                        $this->_update_qc_document_view_permission($customTable['CustomTable']['qc_document_id'],$this->request->data['user_id']);
                    }
                    return true;
                }
                return false;
            }
        }
    }

    public function _update_qc_document_view_permission($qc_document_id = null, $user_id = null){
        if($qc_document_id){
            $this->loadModel('QcDocument');
            $qcDocument = $this->QcDocument->find('first',array('recursive'=>-1,'conditions'=>array('QcDocument.id'=>$qc_document_id)));            
            if($qcDocument){
                $users = json_decode($qcDocument['QcDocument']['user_id'],true);
                if(in_array($user_id, $users)){

                }else{
                    $users[] = $user_id;
                    $qcDocument['QcDocument']['user_id'] = json_encode($users);
                    $this->QcDocument->create();
                    $this->QcDocument->save($qcDocument,false);
                }
            }
        }
        return true;
    }

    public function _removefromarray($arr = null, $val = null){
        if(is_array($arr)){
            foreach($arr as $key => $v){
                if($v == $val){
                    unset($arr[$key]);
                }
            }
            return $arr;
        }

    }
    

    public function recreate_all_forms($autoupdate = null){
        if($autoupdate == 1){
            $this->autoRender = false;
        }
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
        
        $customTables = array();
        $customTables = $this->CustomTable->find('all',array('recursive'=>-1,
            'conditions'=>array(
                'OR'=>array(
                    'CustomTable.custom_table_id ='=>null,
                    'CustomTable.custom_table_id ='=>'',
                )                
            ),            
        ));
        
        if($customTables){
            foreach($customTables as $customTable){
                $tocreate = array();
                $tocreate['CustomTable']['password'] = $customTable['CustomTable']['password'];
                $tocreate['CustomTable']['name'] = $customTable['CustomTable']['name'];
                $tocreate['CustomTable']['table_name'] = $customTable['CustomTable']['table_name'];
                $tocreate['CustomTable']['table_version'] = $customTable['CustomTable']['table_version'];
                $tocreate['CustomTable']['re-password'] = $customTable['CustomTable']['re-password'];
                $tocreate['CustomTable']['fields'] = $customTable['CustomTable']['fields'];
                $tocreate['CustomTable']['description'] = $customTable['CustomTable']['description'];
                $tocreate['CustomTable']['qc_document_id'] = $customTable['CustomTable']['qc_document_id'];
                $tocreate['CustomTable']['count'] = $customTable['CustomTable']['count'];
                $tocreate['CustomTable']['id'] = $customTable['CustomTable']['id'];
                $tocreate['CustomTable']['custom_table_id'] = $customTable['CustomTable']['custom_table_id'];
                $tocreate['CustomTable']['process_id'] = $customTable['CustomTable']['process_id'];
                $tocreate['CustomTable']['table_type'] = $customTable['CustomTable']['table_type'];
                $tocreate['CustomTable']['pre_fields'] = $customTable['CustomTable']['pre_fields'];
                $tocreate['CustomTable']['has_many'] = $customTable['CustomTable']['has_many'];
                $tocreate['CustomTable']['branchid'] = $customTable['CustomTable']['branchid'];
                $tocreate['CustomTable']['departmentid'] = $customTable['CustomTable']['departmentid'];
                $fields = array();
                $fields = json_decode($customTable['CustomTable']['fields'],true);
                
                foreach($fields as $field){
                    $field['field_label'] = base64_decode($field['field_label']);
                    if($field['data_type'] == 'comments'){                        
                        $field['show_comments'] = base64_decode($field['show_comments']);
                        if($this->_isBase64Encoded($field['show_comments'])){
                            $field['show_comments'] = base64_decode($field['show_comments']);
                        }                        
                    }
                    $tocreate['CustomTableFields'][] = $field;
                }                
                $tocreate['linkedTos'] = json_encode($linkedTos);
                $tocreate['linkedTosWithDisplay'] = json_encode($this->_returnDetaultField($fields));
                $this->recreate($customTable['CustomTable']['id'],true,$tocreate);               
            }
        }
        $customTables = array();
        $customTables = $this->CustomTable->find('all',array('recursive'=>-1,
            'conditions'=>array(
                'OR'=>array(
                    'CustomTable.custom_table_id !='=>null,
                    'CustomTable.custom_table_id !='=>'',
                )                
            )
        ));
        $customTable = array();
        if($customTables){
            foreach($customTables as $customTable){
                $tocreate = array();
                $fields = array();
                $tocreate['CustomTable']['password'] = $customTable['CustomTable']['password'];
                $tocreate['CustomTable']['name'] = $customTable['CustomTable']['name'];
                $tocreate['CustomTable']['table_name'] = $customTable['CustomTable']['table_name'];
                $tocreate['CustomTable']['table_version'] = $customTable['CustomTable']['table_version'];
                $tocreate['CustomTable']['re-password'] = $customTable['CustomTable']['re-password'];
                $tocreate['CustomTable']['fields'] = $customTable['CustomTable']['fields'];
                $tocreate['CustomTable']['description'] = $customTable['CustomTable']['description'];
                $tocreate['CustomTable']['qc_document_id'] = $customTable['CustomTable']['qc_document_id'];
                $tocreate['CustomTable']['count'] = $customTable['CustomTable']['count'];
                $tocreate['CustomTable']['id'] = $customTable['CustomTable']['id'];
                $tocreate['CustomTable']['custom_table_id'] = $customTable['CustomTable']['custom_table_id'];
                $tocreate['CustomTable']['process_id'] = $customTable['CustomTable']['process_id'];
                $tocreate['CustomTable']['table_type'] = $customTable['CustomTable']['table_type'];
                $tocreate['CustomTable']['pre_fields'] = $customTable['CustomTable']['pre_fields'];
                $tocreate['CustomTable']['has_many'] = $customTable['CustomTable']['has_many'];
                $tocreate['CustomTable']['branchid'] = $customTable['CustomTable']['branchid'];
                $tocreate['CustomTable']['departmentid'] = $customTable['CustomTable']['departmentid'];
                
                $fields = json_decode($customTable['CustomTable']['fields'],true);
                
                foreach($fields as $field){
                    $field['field_label'] = base64_decode($field['field_label']);
                    if($field['data_type'] == 'comments'){                        
                        $field['show_comments'] = base64_decode($field['show_comments']);
                        if($this->_isBase64Encoded($field['show_comments'])){
                            $field['show_comments'] = base64_decode($field['show_comments']);
                        }                        
                    }
                    $tocreate['CustomTableFields'][] = $field;                                
                }                
                $tocreate['linkedTos'] = json_encode($linkedTos);
                $tocreate['linkedTosWithDisplay'] = json_encode($this->_returnDetaultField($fields));                
                $this->recreate_child($customTable['CustomTable']['id'],true,$tocreate);
            }
        }

        if($autoupdate == 1){
            return true;
        }else{
            $this->Session->setFlash(__('Re-created all the forms.'));
            $this->redirect(array('action' => 'index'));
        }        
    }

    public function custom_table_list($user_id = null){

        if($user_id){
            $this->loadModel('User');
            $user = $this->User->find('first',array('recursive'=>-1,'conditions'=>array('User.id'=>$user_id)));
            $this->set('user',$user);
        }

        $this->paginate = array(
            'limit'=>25,
            'recursive'=>-1,
            'fields'=>array('CustomTable.id','CustomTable.name','CustomTable.creators','CustomTable.editors','CustomTable.viewers','CustomTable.approvers'),
            // 'conditions'=>array('CustomTable.custom_table_id'=> '')
        );
        $customTables = $this->paginate();
        $this->set('customTables',$customTables);

    }    
}
