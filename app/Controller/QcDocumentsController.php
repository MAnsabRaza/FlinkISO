<?php
App::uses('AppController', 'Controller');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');
/**
 * QcDocuments Controller
 *
 * @property QcDocument $QcDocument
 * @property PaginatorComponent $Paginator
 */
class QcDocumentsController extends AppController {
    /**
     * Components
     *
     * @var array
     */
    public $components = array('Paginator');
    public function _get_system_table_id() {
        $this->loadModel('SystemTable');
        $this->SystemTable->recursive = - 1;
        $systemTableId = $this->SystemTable->find('first', array('conditions' => array('SystemTable.system_name' => $this->request->params['controller'])));
        return $systemTableId['SystemTable']['id'];
    }

    public function _check_document_access(){
        $sharing = $this->QcDocument->find('count',array(
            'conditions'=>array(
                'OR'=>array(
                    'QcDocument.branches LIKE' => '%'.$this->Session->read('User.branch_id').'%',
                    'QcDocument.departments LIKE' => '%'.$this->Session->read('User.department_id').'%',
                    'QcDocument.designations LIKE' => '%'.$this->Session->read('User.designation_id').'%',
                    'QcDocument.user_id LIKE' => '%'.$this->Session->read('User.id').'%',
                )
            )
        ));
    }

    public function _doc_access($data = null,$access = null){
        $editors = json_decode($this->request->data['QcDocument']['editors'],true);
        if($editors != null){
            if(!in_array($this->Session->read('User.id'), $editors)){
                if($access['allow_access_user'] == $this->Session->read('User.id')){

                }else{

                    $this->Session->setFlash(__('You do not have permission to edit this document'));
                    $this->redirect(array('controller'=>'users', 'action' => 'access_denied'));
                }
            }    
        }else{
            $this->Session->setFlash(__('You do not have permission to edit this document'));
            $this->redirect(array('controller'=>'users', 'action' => 'access_denied'));
        }
    }

    public function _commons($creator = null) {
        $this->set('branches', $this->_get_branch_list());
        $this->set('departments', $this->_get_department_list());
        $this->set('designations', $this->_get_designation_list());
        $this->set('usernames', $this->_get_usernames());
        if ($this->action == 'view' || $this->action == 'edit') $approvals = $this->get_approvals();
        $qcDocumentCategories = $this->QcDocument->QcDocumentCategory->find('list', array('conditions' => array('QcDocumentCategory.publish' => 1, 'QcDocumentCategory.soft_delete' => 0)));
        if($this->request->data['QcDocument']['standard_id'] && $this->request->data['QcDocument']['standard_id'] != -1){
            $clauses = $this->QcDocument->Clause->find('list', array('conditions' => array('Clause.publish' => 1, 'Clause.soft_delete' => 0,'Clause.standard_id'=>$this->request->data['QcDocument']['standard_id'])));
        }else{
            if($this->request->action != 'index'){
                $clauses = $this->QcDocument->Clause->find('list', array('conditions' => array('Clause.publish' => 1, 'Clause.soft_delete' => 0)));
            }
        }
        
        $standards = $this->QcDocument->Standard->find('list', array('conditions' => array('Standard.publish' => 1, 'Standard.soft_delete' => 0)));
        $approvedBies = $preparedBies = $issuedBies = $issuingAuthorities = $this->QcDocument->IssuingAuthority->find('list', array('conditions' => array('IssuingAuthority.publish' => 1, 'IssuingAuthority.soft_delete' => 0)));
        $crs = $this->QcDocument->Cr->find('list', array('conditions' => array('Cr.publish' => 1, 'Cr.soft_delete' => 0)));
        $oldCrs = $this->QcDocument->OldCr->find('list', array('conditions' => array('OldCr.publish' => 1, 'OldCr.soft_delete' => 0)));
        $parentQcDocuments = $this->QcDocument->ParentQcDocument->find('list', array('conditions' => array('ParentQcDocument.soft_delete' => 0,'ParentQcDocument.archived'=>0)));
        $parentDocuments = $this->QcDocument->ParentDocument->find('list', array('conditions' => array('ParentDocument.soft_delete' => 0,'ParentDocument.archived'=>0)));
        $users = $this->QcDocument->User->find('list', array('conditions' => array('User.publish' => 1, 'User.soft_delete' => 0)));
        $systemTables = $this->QcDocument->SystemTable->find('list', array('conditions' => array('SystemTable.publish' => 1, 'SystemTable.soft_delete' => 0)));
        // $userSessions = $this->QcDocument->UserSession->find('list',array('conditions'=>array('UserSession.publish'=>1,'UserSession.soft_delete'=>0)));
        $companies = $this->QcDocument->Company->find('list', array('conditions' => array('Company.publish' => 1, 'Company.soft_delete' => 0)));
        $schedules = $this->QcDocument->Schedule->find('list', array('conditions' => array('Schedule.publish' => 1, 'Schedule.soft_delete' => 0)));
        $createdBies = $this->_get_user_list();
        $this->set(compact('qcDocumentCategories', 'clauses', 'standards', 'issuingAuthorities', 'crs', 'oldCrs', 'parentQcDocuments', 'parentDocuments', 'users', 'systemTables', 'userSessions', 'companies', 'preparedBies', 'approvedBies', 'issuedBies', 'schedules', 'approvals','createdBies'));
        $count = $this->QcDocument->find('count');
        $published = $this->QcDocument->find('count', array('conditions' => array('QcDocument.publish' => 1)));
        $unpublished = $this->QcDocument->find('count', array('conditions' => array('QcDocument.publish' => 0)));
        $this->set(compact('count', 'published', 'unpublished'));
        $this->set('customArray', $this->QcDocument->customArray);

        // add creator
        if ($this->request->params['named']['approval_id']) {
            $this->get_approval($this->request->params['named']['approval_id'], $creator);
            $this->_get_approval_comnments($this->request->params['named']['approval_id'], $creator);
        }
        $this->_fetch_approval_steps();
    }
    /**
     * index method
     *
     * @return void
     */
    public function index() {        
        $this->QcDocument->virtualFields = array(
            'intdocunumber' => 'CAST(QcDocument.document_number as UNSIGNED)',
            'parent_id'=>'QcDocument.parent_document_id',
            'tables' => 'select count(*) from `custom_tables` where `custom_tables`.`qc_document_id` LIKE QcDocument.id', 
            'active_tables' => 'select count(*) from `custom_tables` where `custom_tables`.`publish` = 1 AND `custom_tables`.`table_locked` = 0 AND `custom_tables`.`qc_document_id` LIKE QcDocument.id',
            'childDoc'=>'select count(*) from qc_documents where qc_documents.parent_document_id LIKE QcDocument.id',
            'srct' => $this->_m9_share_sql() // M9 correction 4 (staging, 26 Oct 2026): corrected sharing rule
        );
        
        $conditions = $this->_check_request();
        if($this->Session->read('User.is_mr') == false){
            $accessConditions = array(
                'QcDocument.archived !='=>1,
                'QcDocument.parent_document_id '=>-1,
                'OR'=>array(
                    'QcDocument.srct >' => 0,
                    'QcDocument.prepared_by' => ((string)$this->Session->read('User.employee_id') === '' ? 'm9-no-employee' : $this->Session->read('User.employee_id')), // M9 correction 4: exact match
                    'QcDocument.approved_by' => ((string)$this->Session->read('User.employee_id') === '' ? 'm9-no-employee' : $this->Session->read('User.employee_id')), // M9 correction 4: exact match
                )                
            );
        }else{
            $accessConditions = array(
                'QcDocument.archived !='=>1,
                'QcDocument.parent_document_id '=>-1,              
            );
        }

        if(isset($this->request->params['named']['standard_id'])){
            $accessConditions[] = array('QcDocument.standard_id'=>$this->request->params['named']['standard_id']);
        }
        
        $this->paginate = array('all',
            'fields'=>array(
                'QcDocument.id', 
                'QcDocument.name',
                'QcDocument.title',
                'QcDocument.document_number',
                'QcDocument.revision_number',
                'QcDocument.document_status',
                'QcDocument.publish',
                'QcDocument.parent_document_id',
                'QcDocument.parent_id',
                'QcDocument.prepared_by',
                'QcDocument.approved_by',
                'QcDocument.tables',
                'QcDocument.active_tables',
                'QcDocument.standard_id',
                'QcDocument.file_type',
                'QcDocument.parent_document_id',
                'QcDocument.childDoc',
                'QcDocument.srct',
                'QcDocument.and_or_condition',
                'QcDocument.branches',
                'QcDocument.departments',
                'QcDocument.designations',
                'QcDocument.reviewed_by',
                'QcDocument.published_by',
                'QcDocument.standard_id',
                'PreparedBy.id',
                'PreparedBy.name',
                'ApprovedBy.id',
                'ApprovedBy.name',
                'IssuedBy.id',
                'IssuedBy.name',
                'Standard.id',
                'Standard.name',
                'ReviewedBy.id',
                'ReviewedBy.name',                
            ),
            'order' => array('QcDocument.intdocunumber' => 'ASC'),
            'conditions' => array(
                $accessConditions
            ));

        $this->QcDocument->recursive = 0;
        $qcDocuments = $this->paginate();
        $this->set('qcDocuments', $qcDocuments);
        $this->_get_count();
        $this->_commons($this->Session->read('User.id'));

        // document count
        if($this->viewVars['standards'] != null){            
            foreach($this->viewVars['standards'] as $standard_id => $standard_name){
                $cTableCount[$standard_id] = $this->QcDocument->find('count',array('conditions'=>array('QcDocument.standard_id'=>$standard_id)));
            }            
            $this->set('cTableCount',$cTableCount);
        }
    }

    public function child_docs($id = null) {
        $this->QcDocument->virtualFields = array(
            'intdocunumber' => 'CAST(QcDocument.document_number as UNSIGNED)',
            'parent_id'=>'QcDocument.parent_document_id',
            'tables' => 'select count(*) from `custom_tables` where `custom_tables`.`qc_document_id` LIKE QcDocument.id',
            'active_tables' => 'select count(*) from `custom_tables` where `custom_tables`.`publish` = 1 AND `custom_tables`.`table_locked` = 0 AND `custom_tables`.`qc_document_id` LIKE QcDocument.id',
            'childDoc'=>'select count(*) from qc_documents where qc_documents.parent_document_id LIKE QcDocument.id');
        
        $conditions = $this->_check_request();        
        if($this->Session->read('User.is_mr') == false){
            $accessConditions = array(
                'OR'=>array(
                    'QcDocument.prepared_by'=>$this->Session->read('User.employee_id'),
                    'QcDocument.branches LIKE' => '%'.$this->Session->read('User.branch_id').'%',
                    'QcDocument.departments LIKE' => '%'.$this->Session->read('User.department_id').'%',
                    // 'QcDocument.designations LIKE' => '%'.$this->Session->read('User.designation_id').'%',
                    'QcDocument.user_id LIKE' => '%'.$this->Session->read('User.id').'%',
                )
            );
        }else{
            $accessConditions = array();
        }

        $childDocArray = array();        
        $qcDocuments = $this->QcDocument->find('all', array(
            'fields'=>array(
                'QcDocument.name','QcDocument.title','QcDocument.document_number','QcDocument.revision_number','QcDocument.document_status','QcDocument.publish','QcDocument.parent_document_id','QcDocument.parent_id','QcDocument.prepared_by','QcDocument.approved_by','QcDocument.tables','QcDocument.active_tables','QcDocument.standard_id','QcDocument.file_type','QcDocument.parent_document_id','QcDocument.childDoc',
                'PreparedBy.id',
                'PreparedBy.name',
                'ApprovedBy.id',
                'ApprovedBy.name',
                'IssuedBy.id',
                'IssuedBy.name',
                'Standard.id',
                'Standard.name'
            ),
            'recursive'=>0,
            'order' => array('QcDocument.intdocunumber' => 'ASC','QcDocument.title'=>'ASC'),
            'conditions' => array(
                'QcDocument.archived !='=>1,
                'QcDocument.parent_document_id'=>$id,
                $accessConditions
            )));        
        
        $this->set('qcDocuments',$qcDocuments);
        $this->_commons($this->Session->read('User.id'));
    }
    /**
     * view method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    public function view($id = null) {
        if (!$this->QcDocument->exists($id)) {
            throw new NotFoundException(__('Invalid document'));
        }
        
        $options = array('conditions' => array('QcDocument.' . $this->QcDocument->primaryKey => $id));        
        $qcDocument = $this->QcDocument->find('first', $options);

        if($this->request->data['Access']['allow_access_user'] == $this->Session->read('User.id')){
            if($this->request->data['Access']['approval_mode'] == 0 && $qcDocument['QcDocument']['prepared_by'] == $this->Session->read('User.employee_id')){
                $this->redirect(array('action' => 'edit', $id, 
                    'approval_id'=> trim($this->request->params['named']['approval_id']),
                    'approval_step_id'=> trim($this->request->params['named']['approval_step_id']),
                    'approval_comment_id'=> $this->request->params['named']['approval_comment_id'],
                    'qc_document_id'=> $id,
                    'custom_table_id'=> $this->request->params['named']['custom_table_id'],
                    'prepared_by'=>$qcDocument['QcDocument']['prepared_by'],
                    'timestamp'=>date('ymdhis')
                ));
            }
        }else{
            $this->_check_access();
        }
        
        if ($this->request->is('post') || $this->request->is('put')) {
            $this->request->data['QcDocument']['id'] = $id; $this->request->data['Approval']['QcDocument']['id'] = $id; // M9 correction 12: this document only
            $this->Session->setFlash(__('Approval Saved'));
            // M9 correction 29 (owner review 9 Nov 2026): every approval write is checked; if one fails, the writes made by this
            // request are undone and the page says so instead of "Approval Saved".
            $this->_m9_approval_begin(); $m9ok = true;
            if ($this->request->data['Approval']['user_id']) $m9ok = $this->_save_approvals();
            // M9 fix (staging, 14 Oct 2026): the forward form on this page posts Approval.QcDocument.user_id, which was never read here.
            if ($m9ok && !empty($this->request->data['Approval']['QcDocument']['user_id'])) {
                $this->request->data['Approval']['QcDocument']['id'] = $id;
                $this->request->data['QcDocument']['id'] = $id;
                $this->request->data['QcDocument']['model_name'] = 'QcDocument';
                if (empty($this->request->data['Approval']['QcDocument']['from'])) $this->request->data['Approval']['QcDocument']['from'] = $this->Session->read('User.id');
                $m9ok = $this->_save_approvals($id);
            }
            if ($m9ok && $this->request->data['ApprovalComment']['user_id']) $m9ok = $this->_save_approval_comments();
            if ($m9ok) $this->_m9_approval_commit(); else $this->_m9_approval_abort_flash();
            $this->redirect(array('action' => 'view', $id));
        }
        

        $this->QcDocument->virtualFields = array(
            'childDoc'=>'select count(*) from qc_documents where qc_documents.parent_document_id LIKE QcDocument.id',
        );
        

        $this->set('qcDocument', $qcDocument);
        $this->_commons($qcDocument['QcDocument']['created_by']);
        $this->set('childDocs', $this->_get_child_docs($id));
        if($this->request->data['QcDocument']['archived'] == 1){
            $this->Session->setFlash(__('You can not edit this document, its archived.'));
            $this->redirect(array('action' => 'view', $id,'timestamp'=>date('ymdhis')));
        }
        // redirect to update_revision of document_status == 6
        if($qcDocument['QcDocument']['document_status'] == 6 && $qcDocument['QcDocument']['archived'] == 0){
            $this->redirect(array('action' => 'update_revision', $id));
        }
        // get CR record count
        $this->set('crs',$this->change_history($id));
    }

    public function view_archived($id = null) {
        if (!$this->QcDocument->exists($id)) {
            throw new NotFoundException(__('Invalid document'));
        }
        if ($this->request->is('post') || $this->request->is('put')) {
            $this->request->data['QcDocument']['id'] = $id; $this->request->data['Approval']['QcDocument']['id'] = $id; // M9 correction 12: this document only
            $this->Session->setFlash(__('Approval Saved'));
            $this->_m9_approval_begin(); $m9ok = true; // M9 correction 29
            if ($this->request->data['Approval']['user_id']) $m9ok = $this->_save_approvals();
            if ($m9ok && $this->request->data['ApprovalComment']['user_id']) $m9ok = $this->_save_approval_comments();
            if ($m9ok) $this->_m9_approval_commit(); else $this->_m9_approval_abort_flash();
            $this->redirect(array('action' => 'view', $id));
        }
        $options = array('conditions' => array('QcDocument.' . $this->QcDocument->primaryKey => $id));        
        $qcDocument = $this->QcDocument->find('first', $options);
        // M9 correction 22 (finding 2): viewing an archived document changes nothing. The viewer gets a key for this page only;
        // it is not saved (it used to be saved on every visit, built from a posted id that a GET request does not carry).
        $qcDocument['QcDocument']['file_key'] = $this->_generate_onlyoffice_key($id . 'v' . date('YmdHis'));
        $this->set('qcDocument', $qcDocument);
        $this->_commons($qcDocument['QcDocument']['created_by']);
        $this->set('childDocs', $this->_get_child_docs($id));
    }
    /**
     * view method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    public function mini_view($id = null) {
        if (!$this->QcDocument->exists($id)) {
            throw new NotFoundException(__('Invalid document'));
        }
        $options = array('conditions' => array('QcDocument.' . $this->QcDocument->primaryKey => $id));
        $qcDocument = $this->QcDocument->find('first', $options);
        $this->set('qcDocument', $qcDocument);
        $this->set('document', $qcDocument);
        $this->_commons($qcDocument['QcDocument']['created_by']);
        // $this->set('childDocs',$this->_get_child_docs($id));
        // $this->_qc_document_header($qcDocument['QcDocument']['qc_document_id']);        
    }
    public function _get_child_docs($id = null) {
        $childDocs = $this->QcDocument->find('all', array('recursive' => 0, 'conditions' => array('QcDocument.parent_document_id' => $id)));
        return $childDocs;
    }
    /**
     * list method
     *
     * @return void
     */
    public function lists() {
        $this->_get_count();
    }
    /**
     * add method
     *
     * @return void
     */
    public function add() {
        if ( ! is_writable(dirname(WWW_ROOT . DS . 'files' . DS . $this->Session->read('User.company_id')))) {
            $this->Session->setFlash(__('Files directory is not writable. Make sure the "'.WWW_ROOT . DS . 'files" folder is writable by the site\'s PHP user'));
            $this->redirect(array('action' => 'index'));
        }
        if($this->Session->read('User.is_creator') == 0){
            $this->Session->setFlash(__('You are not authorized to create new documents'));
            $this->redirect(array('action' => 'index'));
        }
        if(file_exists(Configure::read('path'))){
        }else{
            $newFolder = New Folder();
            $newFolder->create(Configure::read('path'));
        }
        
        $this->_m9_mode(Configure::read('path'));
        if ($this->request->is('post') || $this->request->is('put')) {
            $accessptedExtentions = array(
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.oasis.opendocument.spreadsheet',
                'application/vnd.oasis.opendocument.text',
                'application/rtf',
                'text/plain',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation'
            );
            
            if($this->request->data['QcDocument']['file']['type']){
                if(!in_array($this->request->data['QcDocument']['file']['type'], $accessptedExtentions)){
                    $this->Session->setFlash(__('Incorrect File Type'));
                    $this->redirect(array('action' => 'add'));
                }
            }

            if(!$this->request->data['QcDocument']['file']['name']){
                unset($this->request->data['QcDocument']['file']);
            }

            $this->request->data['QcDocument']['system_table_id'] = $this->_get_system_table_id();
            $this->QcDocument->create();
            
            $this->request->data['QcDocument']['title'] = $this->_clean_table_names(ltrim(rtrim($this->request->data['QcDocument']['title'])));
            $this->request->data['QcDocument']['document_number'] = ltrim(rtrim($this->request->data['QcDocument']['document_number']));
            $this->request->data['QcDocument']['revision_number'] = ltrim(rtrim($this->request->data['QcDocument']['revision_number']));
            $m9why = $this->_m9_revision_check($this->request->data['QcDocument']['revision_number']); // M9 correction 22
            if ($m9why !== '') { $this->Session->setFlash($m9why); $this->redirect(array('action' => 'add')); }
            $this->request->data['QcDocument']['branches'] = json_encode($this->request->data['QcDocument']['branches']);
            $this->request->data['QcDocument']['departments'] = json_encode($this->request->data['QcDocument']['departments']);
            $this->request->data['QcDocument']['designations'] = json_encode($this->request->data['QcDocument']['designations']);
            $this->request->data['QcDocument']['user_id'] = json_encode($this->request->data['QcDocument']['user_id']);
            $this->request->data['QcDocument']['editors'] = json_encode($this->request->data['QcDocument']['editors']);
            $this->request->data['QcDocument']['linked_document_ids'] = json_encode($this->request->data['QcDocument']['linked_document_ids']);
            $this->request->data['QcDocument']['additional_clauses'] = json_encode($this->request->data['QcDocument']['additional_clauses']);
            // $this->request->data[$this->modelClass]['publish'] = $this->request->data['Approval']['publish'];
            $this->request->data['QcDocument']['version'] = 1;

            $this->request->data['QcDocument']['approval_step_id'] = $this->request->data['Approval']['QcDocument']['approval_step_id'];

            if($this->request->data['QcDocument']['select_all_branches'] == 1){
                $this->request->data['QcDocument']['branches'] = json_encode(array_keys($this->_get_branch_list()));
            }
            if($this->request->data['QcDocument']['select_all_departments'] == 1){
                $this->request->data['QcDocument']['departments'] = json_encode(array_keys($this->_get_department_list()));
            }
            if($this->request->data['QcDocument']['select_all_designations'] == 1){
                $this->request->data['QcDocument']['designations'] = json_encode(array_keys($this->_get_designation_list()));
            }
            if($this->request->data['QcDocument']['select_all_users'] == 1){
                $this->request->data['QcDocument']['user_id'] = json_encode(array_keys($this->_get_user_list()));
            }
            if($this->request->data['Approval']['QcDocument']['publish'] == 1){
                $this->request->data['QcDocument']['publish'] = 1;
                $this->request->data['QcDocument']['approved_by'] = $this->request->data['Approval']['QcDocument']['approved_by'];                
            }

            $document_number = $this->request->data['QcDocument']['document_number'];
            $rec = $this->QcDocument->find('count', array('conditions' => array('QcDocument.document_number' => $document_number)));
            if ($rec > 0) {
                $document_number = $this->_get_last_number();
                $new_document_number = $document_number;
            } else {
                $new_document_number = $document_number;
            }

            $this->request->data['QcDocument']['document_number'] = $new_document_number;
            if ($this->QcDocument->save($this->request->data)) {
                if (!empty($this->request->data['QcDocument']['file']['name'])) $this->_step1($this->request->data, $this->QcDocument->id);
                else{
                    // check if the tmp file is available
                    // if yes copy that file here
                    if(!empty($this->request->data['QcDocument']['template_id'])){
                        // load template file details
                        $this->loadModel('Template');
                        $templateFile = $this->Template->find('first',array('recursive'=>-1, 'fields'=>array('Template.id','Template.name','Template.file_type'), 'conditions'=>array('Template.id'=>$this->request->data['QcDocument']['template_id'])));

                        if($templateFile){
                            $file_type = $templateFile['Template']['file_type'];
                            $file_name = $this->_clean_table_names($templateFile['Template']['name']);
                            $from = WWW_ROOT . DS . 'files' . DS . $this->Session->read('User.company_id') . DS . 'templates' .DS . $templateFile['Template']['id'] . DS . $file_name. '.'.$file_type ;
                        }else{
                            $this->Session->setFlash(__('Unable to add template.'));
                            $this->redirect(array('action' => 'add'));
                        }

                        $file_name = $this->request->data['QcDocument']['title'];
                        $document_number = $this->request->data['QcDocument']['document_number'];
                        $document_version = $this->request->data['QcDocument']['revision_number'];
                        $file_name = $document_number . '-' . $file_name . '-' . $document_version;
                        $file_name = $this->_clean_table_names($file_name);
                        $file_name = $file_name. '.' . $file_type;

                        $id = $this->QcDocument->id;
                        $file = Configure::read('path') . DS . $id . DS . $file_name;

                        $to = Configure::read('path') . DS . $id;
                        $history_file_for_save = $file;

                        if(!file_exists($to)){
                            $testfolder = new Folder($to);
                            $testfolder->create($to);
                            $this->_m9_mode($to);
                        }

                        $to = $file;
                        $fromFile = new File($from);
                        $fromFile->copy($to,true);
                        if(copy($from,$to)){
                            $key = $this->_generate_onlyoffice_key($this->request->data['QcDocument']['id'] . date('Ymdhis'));
                            $this->set('file_key', $key);
                            $qcdoc = $this->QcDocument->find('first', array('recursive' => - 1, 'conditions' => array('QcDocument.id' => $id)));
                            $qcdoc['QcDocument']['file_key'] = $key;
                            $qcdoc['QcDocument']['file_type'] = $file_type;
                            $qcdoc['QcDocument']['file_key'] = $key;
                            $this->QcDocument->create();
                            $this->QcDocument->save($qcdoc['QcDocument']);

                            $json = [
                                "created" => date("Y-m-d H:i:s"),
                                'uid'=>$this->Session->read('User.id'),
                                'name'=>$this->Session->read('User.name'),
                            ];

                            $version = 1;
                            $history_file_for_save = $history_file_for_save.'-hist' . DS . $version;
                            $historyFolder = new Folder($history_file_for_save);
                            $historyFolder->create($history_file_for_save);
                            $this->_m9_mode($history_file_for_save);
                            file_put_contents($history_file_for_save . DS . "createdInfo.json", json_encode($json, JSON_PRETTY_PRINT));
                        }else{
                            $this->Session->setFlash(__('Unable to copy template.'));
                            $this->redirect(array('action' => 'add'));
                        }
                    }
                }
                $m9msg = __('The document has been saved');
                if ($this->_show_approvals()) { // M9 correction 29: a failed approval request is undone and reported
                    $this->_m9_approval_begin();
                    if ($this->_save_approvals($this->QcDocument->id)) $this->_m9_approval_commit();
                    else $m9msg = __('The document has been saved, but the approval request could not be sent.') . ($this->_m9_undo() ? '' : ' ' . __('Some changes could not be undone; this is recorded in the application log.'));
                }
                if($this->request->data['QcDocument']['document_type'] == 2)$this->_add_process($this->request->data,$this->QcDocument->id);
                $this->Session->setFlash($m9msg);
                $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash(__('The document could not be saved. Please, try again.'));
            }
        }
        $this->_commons();
        if (isset($this->request->params['named']['parent_id'])) {
            $document = $this->QcDocument->find('first', array('recursive' => 0, 'conditions' => array('QcDocument.id' => $this->request->params['named']['parent_id'])));
            if ($document) {
                $this->set('document', $document);
            }
        }
        $this->loadModel('Template');
        $templates = $this->Template->find('all',array('recursive'=>-1, 'conditions'=>array('Template.model'=>'Template')));
        $this->set('templates',$templates);
        $document_number = $this->_get_last_number();
        $this->set('document_number',$document_number);
    }
    

    public function _step1($data = null, $id = null) {
        $full_name = explode('.', $data['QcDocument']['file']['name']);
        $file_type = $data['QcDocument']['file_type'];
        $file_name = $data['QcDocument']['title'];
        $document_number = $data['QcDocument']['document_number'];
        $document_version = (int)$data['QcDocument']['revision_number'];
        $title = $file_name;
        $file_name = $document_number . '-' . $file_name . '-' . $document_version;
        $file_name = $this->_clean_table_names($file_name);
        $file_name = $file_name . '.' . $file_type;
        
        $history_file_for_save = $file = Configure::read('path') . DS . $id . DS . $file_name;
        if (!file_exists(Configure::read('path') . DS . $id)) {
            $pathfolder = new Folder();
            if ($pathfolder->create(Configure::read('path') . DS . $id)) {
                $this->_m9_mode(Configure::read('path') . DS . $id);
            } else {
                echo "failed folder" . Configure::read('path');
            }
        }
        if (move_uploaded_file($data['QcDocument']['file']['tmp_name'], $file)) {
            $key = $this->_generate_onlyoffice_key($this->data['QcDocument']['id'] . date('Ymdhis'));
            $this->set('file_key', $key);
            $qcdoc = $this->QcDocument->find('first', array('recursive' => - 1, 'conditions' => array('QcDocument.id' => $id)));
            $qcdoc['QcDocument']['title'] = $title;
            $qcdoc['QcDocument']['file_key'] = $key;
            $data['QcDocument']['file_key'] = $key;
            $data['QcDocument']['file_status'] = 0;
            $data['QcDocument']['version'] = 1;
            $data['QcDocument']['versions'] = json_encode(array());
            $this->QcDocument->create();
            $this->QcDocument->save($qcdoc['QcDocument']);

            $json = [
                "created" => date("Y-m-d H:i:s"),
                'uid'=>$this->Session->read('User.id'),
                'name'=>$this->Session->read('User.name'),
            ];

            $version = 1;
            $history_file_for_save = $history_file_for_save.'-hist' . DS . $version;
            $historyFolder = new Folder($history_file_for_save);
            $historyFolder->create($history_file_for_save);
            $this->_m9_mode($history_file_for_save);
            file_put_contents($history_file_for_save . DS . "createdInfo.json", json_encode($json, JSON_PRETTY_PRINT));
        } else {
            echo "File move failed!";
        }
        if ($this->data['QcDocument']['file_key']) {
        } else {
            $key = $this->_generate_onlyoffice_key($this->data['QcDocument']['id'] . date('Ymdhis'));
            $this->data['QcDocument']['file_key'] = $key;
            $this->set('file_key', $key);
            $qcdoc = $this->QcDocument->find('first', array('recursive' => - 1, 'conditions' => array('QcDocument.id' => $id)));
            $qcdoc['QcDocument']['title'] = $title;
            $data['QcDocument']['file_key'] = $key;
            $qcdoc['QcDocument']['file_key'] = $key;
            $data['QcDocument']['file_status'] = 0;
            $this->QcDocument->create();
            $this->QcDocument->save($qcdoc['QcDocument']);

            $json = [
                "created" => date("Y-m-d H:i:s"),
                'uid'=>$this->Session->read('User.id'),
                'name'=>$this->Session->read('User.name'),
            ];

            $version = 1;
            $history_file_for_save = $history_file_for_save.'-hist' . DS . $version;
            $historyFolder = new Folder($history_file_for_save);
            $historyFolder->create($history_file_for_save);
            $this->_m9_mode($history_file_for_save);
            file_put_contents($history_file_for_save . DS . "createdInfo.json", json_encode($json, JSON_PRETTY_PRINT));
        }
    }


    public function upload_qc_document(){
        if ($this->request->is('post') || $this->request->is('put')) {

            $accessptedExtentions = array(
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.oasis.opendocument.spreadsheet',
                'application/vnd.oasis.opendocument.text',
                'application/rtf',
                'text/plain',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/pdf'
            );
            
            if($this->request->data['QcDocument']['file']['type']){
                if(!in_array($this->request->data['QcDocument']['file']['type'], $accessptedExtentions)){
                    $this->Session->setFlash(__('Incorrect File Type'));
                    $this->redirect(array('action' => 'view',$this->request->data['QcDocument']['id']));
                }
            }
            
            $full_name = explode('.', $this->request->data['QcDocument']['file']['name']);
            $file_type = $this->request->data['QcDocument']['file_type'];

            // if(!$full_name)$full_name = $this->request->data['QcDocument']['file']['type'];
            if(!$file_type)$file_type = $this->request->data['QcDocument']['file']['type'];

            //get qc doc
            $qcDocument = $this->QcDocument->find('first',array(
                'recursive'=>-1,
                'fields'=>array('QcDocument.id','QcDocument.document_number','QcDocument.revision_number','QcDocument.title'),
                'conditions'=>array('QcDocument.id'=>$this->request->data['QcDocument']['id'])));

            if(!$qcDocument){
                $this->Session->setFlash(__('Incorrect document'));
                $this->redirect(array('action' => 'view',$this->request->data['QcDocument']['id']));
            }

            $file_name = $qcDocument['QcDocument']['title'];
            $document_number = $qcDocument['QcDocument']['document_number'];
            $document_version = $qcDocument['QcDocument']['revision_number'];
            $file_name = $document_number . '-' . $qcDocument['QcDocument']['title'] . '-' . $document_version;
            $file_name = $file_name_for_folder = $this->_clean_table_names($file_name);
            $file_name = $file_name. '.' . $file_type;            
            $id = $this->request->data['QcDocument']['id'];            
            $file = Configure::read('path') . DS . $id . DS . $file_name;

            if (!file_exists(Configure::read('path') . DS . $id)) {
                $pathfolder = new Folder();
                if ($pathfolder->create(Configure::read('path') . DS . $id,false,0755)) {
                } else {
                    $this->Session->setFlash(__('failed folder' . Configure::read('path')));
                    $this->redirect(array('action' => 'view',$this->request->data['QcDocument']['id']));
                }
            }else{
                $path = Configure::read('path') . DS . $id ;
                $dir = new Folder();
                $dir->create($path);
                $this->_m9_mode($path);                
            }

            $folder_new_file = New Folder();
            $folder_new_file->create(Configure::read('path') . DS . $id . DS . $file_name_for_folder, false, 0755);
            
            if (move_uploaded_file($this->request->data['QcDocument']['file']['tmp_name'], $file)) {
                $key = $this->_generate_onlyoffice_key($this->request->data['QcDocument']['id'] . date('Ymdhis'));
                $this->set('file_key', $key);
                $qcdoc = $this->QcDocument->find('first', array('recursive' => - 1, 'conditions' => array('QcDocument.id' => $id)));
                $qcdoc['QcDocument']['file_key'] = $key;
                $qcod['QcDocument']['file_status'] = 0;
                $qcdoc['QcDocument']['file_name'] = $file_name;
                $qcdoc['QcDocument']['file_type'] = $file_type;
                $this->request->data['QcDocument']['file_key'] = $key;
                $this->QcDocument->create();
                $this->QcDocument->save($qcdoc['QcDocument'],false);
                $this->Session->setFlash(__('File Uploaded'));

                $json = [
                    "created" => date("Y-m-d H:i:s"),
                    'uid'=>$this->Session->read('User.id'),
                    'name'=>$this->Session->read('User.name'),
                ];

                $version = 1;
                $history_file_for_save = $history_file_for_save.'-hist' . DS . $version;
                $historyFolder = new Folder($history_file_for_save);
                $historyFolder->create($history_file_for_save);
                $this->_m9_mode($history_file_for_save);
                file_put_contents($history_file_for_save . DS . "createdInfo.json", json_encode($json, JSON_PRETTY_PRINT));

                // now copy new file to custom tables
                $customTables = $this->QcDocument->CustomTable->find('all',array('recursive'=>-1,
                // 'fields'=>array('CustomTable.id','CustomTable.file_key','CustomTable.version_keys','CustomTable.file_status'),
                    'conditions'=>array('CustomTable.qc_document_id'=>$this->request->data['QcDocument']['id'])));
                if($customTables){
                    foreach($customTables as $customTable){
                        $this->QcDocument->CustomTable->create();
                        $key = $this->_generate_onlyoffice_key($customTable['CustomTable']['id'] . date('Ymdhis'));
                        $customTable['CustomTable']['file_key'] = $key;
                        $customTable['CustomTable']['version_keys'] = $key;
                        $this->QcDocument->CustomTable->save($customTable,false);

                        // delete old file
                        $customTableFolder = Configure::read('path') . DS . $customTable['CustomTable']['id'];
                        $customTableFolder = str_replace('qc_documents','custom_tables',Configure::read('path') . DS . $customTable['CustomTable']['id']);
                        $delFolder = new Folder($customTableFolder);
                        
                        $dir = new Folder();
                        $dir->create($customTableFolder, false, 0755);
                        $to = $customTableFolder . DS . $file_name;
                        copy($file,$to);
                    }
                }
            } else {
                $this->Session->setFlash(__('File move failed....'));
                $this->redirect(array('action' => 'view',$this->request->data['QcDocument']['id']));
            }            
            $this->redirect(array('action' => 'view',$this->request->data['QcDocument']['id']));
        }
    }
    /**
     * edit method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    public function edit($id = null) {
        if (!$this->QcDocument->exists($id)) {
            throw new NotFoundException(__('Invalid document'));
        }

        if ($this->_show_approvals()) {
            $this->set(array('showApprovals' => $this->_show_approvals()));
        }
        
        if (($this->request->is('post') || $this->request->is('put')) && !empty($this->request->data['QcDocument'])) {
            $this->request->data['QcDocument']['id'] = $id; // M9 correction 12: the document saved is the document in the address, which was checked
            if (array_key_exists('revision_number', $this->request->data['QcDocument'])) { // M9 correction 22d (owner, 6 Nov 2026)
                $m9why = $this->_m9_revision_unchanged($this->request->data['QcDocument']['revision_number'], $id);
                if ($m9why !== '') { $this->_m9_ac_log('revision_change_refused', 'edit'); $this->Session->setFlash($m9why); $this->redirect(array('action' => 'edit', $id)); }
                $this->request->data['QcDocument']['revision_number'] = (string)$this->QcDocument->field('revision_number', array('QcDocument.id' => $id));
            }
            // M9 correction 27 (owner, 6 Nov 2026): the linked processes are updated only after the document itself was saved (below)
            $this->request->data['QcDocument']['system_table_id'] = $this->_get_system_table_id();
            $this->request->data['QcDocument']['branches'] = json_encode($this->request->data['QcDocument']['share_with_branches']);
            $this->request->data['QcDocument']['departments'] = json_encode($this->request->data['QcDocument']['share_with_departments']);
            $this->request->data['QcDocument']['designations'] = json_encode($this->request->data['QcDocument']['share_with_designations']);
            $this->request->data['QcDocument']['user_id'] = json_encode($this->request->data['QcDocument']['user_id']);
            if (isset($this->request->data['QcDocument']) && array_key_exists('editors', $this->request->data['QcDocument'])) $this->request->data['QcDocument']['editors'] = json_encode($this->request->data['QcDocument']['editors']); // M9 correction 15: a save without the editors field keeps the stored list
            $this->request->data['QcDocument']['linked_document_ids'] = json_encode($this->request->data['QcDocument']['linked_document_ids']);
            $this->request->data['QcDocument']['additional_clauses'] = json_encode($this->request->data['QcDocument']['additional_clauses']);
            $this->request->data['QcDocument']['publish'] = $this->request->data['Approval']['QcDocument']['publish'];
            // if($this->request->data['Approval'][0]['approval_step_id'])$this->request->data['QcDocument']['approval_step_id'] = $this->request->data['Approval'][0]['approval_step_id'];
            // else $this->request->data['QcDocument']['approval_step_id'] = $this->request->data['Approval']['QcDocument']['approval_step_id'];


            

            if($this->request->data['QcDocument']['select_all_branches'] == 1){
                $this->request->data['QcDocument']['branches'] = json_encode(array_keys($this->_get_branch_list()));
            }
            if($this->request->data['QcDocument']['select_all_departments'] == 1){
                $this->request->data['QcDocument']['departments'] = json_encode(array_keys($this->_get_department_list()));
            }
            if($this->request->data['QcDocument']['select_all_designations'] == 1){
                // $this->request->data['QcDocument']['designations'] = json_encode(array_keys($this->_get_designation_list()));
            }
            if($this->request->data['QcDocument']['select_all_users'] == 1){
                $this->request->data['QcDocument']['user_id'] = json_encode(array_keys($this->_get_user_list()));
            }
            
            unset($this->request->data['QcDocument']['publish']);
            // if($this->request->data['Approval']['QcDocument']['publish'] == 1){
            //     $this->request->data['QcDocument']['publish'] = 1;
            //     $this->request->data['QcDocument']['approved_by'] = $this->request->data['Approval']['QcDocument']['approved_by'];
            //     if($this->request->data['QcDocument']['prepared_by'] == -1)$this->request->data['QcDocument']['prepared_by'] = $this->request->data['Approval']['QcDocument']['prepared_by'];
            // }

            // M9 correction 27 (owner, 6 Nov 2026): the document is validated and saved first; only then are the linked processes
            // updated, each save checked. On any failure the document and every process are put back exactly as they were
            // (the tables are MyISAM, so there is no transaction) and nothing is reported as saved.
            $m9docBefore = $this->QcDocument->find('first', array('recursive' => -1, 'conditions' => array('QcDocument.id' => $id)));
            $this->loadModel('Process'); $m9procBefore = $this->Process->find('all', array('recursive' => -1, 'conditions' => array('Process.qc_document_id' => $id))); // M9 correction 29: put back if the approval step fails
            try { $m9saved = (bool)$this->QcDocument->save($this->request->data); } // a database error throws in CakePHP 2
            catch (Exception $m9e) { $m9saved = false; $m9back = $this->_m9_put_back('QcDocument', isset($m9docBefore['QcDocument']) ? $m9docBefore['QcDocument'] : null); $this->_m9_ac_log('edit_failed_restored', substr((string)$id, 0, 8) . ' document_save_error' . ($m9back ? '' : ' document_restore_failed:' . $this->m9PutBackDiff)); }
            if ($m9saved && $this->request->data['QcDocument']['document_type'] == 2) {
                $m9proc = $this->_update_process($this->request->data, $id);
                if ($m9proc !== true) {
                    $m9back = $this->_m9_put_back('QcDocument', isset($m9docBefore['QcDocument']) ? $m9docBefore['QcDocument'] : null);
                    $this->_m9_ac_log('edit_failed_restored', substr((string)$id, 0, 8) . ' ' . $m9proc . ($m9back ? '' : ' document_restore_failed:' . $this->m9PutBackDiff));
                    $m9saved = false;
                }
            }
            if ($m9saved) {
                // M9 correction 29 (owner review 9 Nov 2026, finding 1): the approval request and the approval comment are checked.
                // If either fails, everything this save wrote is undone, the document and its linked processes are put back as
                // they were, and nothing is reported as saved.
                $this->_m9_approval_begin(); $m9ok = true;
                if ($this->request->data['Approval']['QcDocument']['user_id']) $m9ok = $this->_save_approvals($this->QcDocument->id);
                if ($m9ok && $this->request->data['ApprovalComment']['user_id']) $m9ok = $this->_save_approval_comments();
                if ($m9ok) $this->_m9_approval_commit();
                else {
                    $m9undo = $this->_m9_undo();
                    $m9pb = true; foreach ($m9procBefore as $m9p) if (isset($m9p['Process']) && false === $this->_m9_put_back('Process', $m9p['Process'])) $m9pb = false;
                    $m9back = $this->_m9_put_back('QcDocument', isset($m9docBefore['QcDocument']) ? $m9docBefore['QcDocument'] : null);
                    $this->_m9_ac_log('edit_failed_restored', substr((string)$id, 0, 8) . ' ' . $this->m9ApprovalError . ($m9undo ? '' : ' approval_undo_failed') . ($m9pb ? '' : ' process_restore_failed') . ($m9back ? '' : ' document_restore_failed:' . $this->m9PutBackDiff));
                    $m9saved = false;
                }
            }
            if ($m9saved) {
                if ($this->_show_evidence() == true) $this->redirect(array('action' => 'view', $id));
                else                
                    $this->redirect(array('action' => 'view', $id,'timestamp'=>date('ymdhis')));
            } else {
                $this->Session->setFlash(__('The document could not be saved. Please, try again.') . ($this->m9ApprovalMessage !== '' ? ' ' . __($this->m9ApprovalMessage) : ''));
            }
        } else {
            $access = $this->request->data['Access'];
            $options = array('conditions' => array('QcDocument.' . $this->QcDocument->primaryKey => $id));
            $this->request->data = $this->QcDocument->find('first', $options);
            if($this->Session->read('User.id') != $this->request->data['QcDocument']['created_by'])$this->_doc_access($this->request->data,$access);

            if($this->request->data['QcDocument']['document_status'] == 3){
                $this->Session->setFlash(__('Can not edit this document. This document is under revision.'));
                $this->redirect(array('action' => 'view', $id,'timestamp'=>date('ymdhis')));
            }            
            if($this->request->data['QcDocument']['document_status'] == 6){
                $this->Session->setFlash(__('You must update this document.'));
                $this->redirect(array('action' => 'update_revision', $id,'timestamp'=>date('ymdhis')));
            }
            if($this->request->data['QcDocument']['archived'] == 1){
                $this->Session->setFlash(__('You can not edit this document, its archived.'));
                $this->redirect(array('action' => 'view', $id,'timestamp'=>date('ymdhis')));
            }
            
            $file_type = $this->request->data['QcDocument']['file_type'];
            $file_name = $this->request->data['QcDocument']['title'];
            $document_number = $this->request->data['QcDocument']['document_number'];
            $document_version = $this->request->data['QcDocument']['revision_number'];
            $file_name = $document_number . '-' . $file_name . '-' . $document_version;
            $file_name = $this->_clean_table_names($file_name);
            $file_name = $file_name. '.' . $file_type;

            $file = Configure::read('path') . DS . $this->request->data['QcDocument']['id'] . DS . $file_name;
            if (!file_exists(Configure::read('path') . DS . $this->request->data['QcDocument']['id'])) {
                $pathfolder = new Folder();
                if ($pathfolder->create(Configure::read('path') . DS . $this->request->data['QcDocument']['id'])) {
                    $this->_m9_mode(Configure::read('path') . DS . $this->request->data['QcDocument']['id']);
                } else {
                    echo "failed folder" . Configure::read('path');
                }
            }
            if (!file_exists($file)) {
                $from = WWW_ROOT . 'files/samples/sample.' . $file_type;
                if (file_exists($from)) {
                    copy($from, $file);
                    $key = $this->_generate_onlyoffice_key($this->data['QcDocument']['id'] . date('Ymdhis'));
                    $this->set('file_key', $key);
                    $qcdoc = $this->QcDocument->find('first', array('recursive' => - 1, 'conditions' => array('QcDocument.id' => $this->request->data['QcDocument']['id'])));
                    $qcdoc['QcDocument']['file_key'] = $key;
                    $qcdoc['QcDocument']['file_status'] = 0;
                    $this->request->data['QcDocument']['file_key'] = $key;
                    $this->QcDocument->create();
                    $this->QcDocument->save($qcdoc['QcDocument']);
                }
            }
            if ($this->data['QcDocument']['file_key']) {

            } else {
                $key = $this->_generate_onlyoffice_key($this->data['QcDocument']['id'] . date('Ymdhis'));
                $this->data['QcDocument']['file_key'] = $key;
                $this->set('file_key', $key);
                $qcdoc = $this->QcDocument->find('first', array('recursive' => - 1, 'conditions' => array('QcDocument.id' => $this->request->data['QcDocument']['id'])));
                $this->request->data['QcDocument']['file_key'] = $key;
                $qcdoc['QcDocument']['file_key'] = $key;
                $qcdoc['QcDocument']['file_status'] = 0;
                $this->QcDocument->create();
                $this->QcDocument->save($qcdoc['QcDocument']);
            }
        }
        $this->_commons($this->request->data['QcDocument']['created_by']);
        if($this->Session->read('User.id') != $this->request->data['QcDocument']['created_by'])$this->_doc_access($this->request->data,$access);

        // check all approvals
        $this->loadModel('Approval');
        $pandingApprovals = $this->Approval->find('count',array(            
            'conditions'=>array(
                'Approval.model_name'=>'QcDocument',
                'Approval.record'=>$id,
                'Approval.approval_status'=>0
            )));
        
        $this->set('pandingApprovals',$pandingApprovals);

    }
    /**
     * approve method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    public function approve($id = null, $approvalId = null) {
        if (!$this->QcDocument->exists($id)) {
            throw new NotFoundException(__('Invalid document'));
        }
        $this->loadModel('Approval');
        if (!$this->Approval->exists($approvalId)) {
            throw new NotFoundException(__('Invalid approval id'));
        }
        $approval = $this->Approval->read(null, $approvalId);
        $this->set('same', $approval['Approval']['user_id']);
        if ($this->_show_approvals()) {
            $this->set(array('showApprovals' => $this->_show_approvals()));
        }
        if ($this->request->is('post') || $this->request->is('put')) {
            $this->request->data['QcDocument']['id'] = $id; $this->request->data['Approval']['QcDocument']['id'] = $id; // M9 correction 12: this document only
            if ($this->QcDocument->save($this->request->data)) {
                if (!isset($this->request->data[$this->modelClass]['publish']) && $this->request->data['Approval']['user_id'] != - 1) {
                    $this->request->data[$this->modelClass]['publish'] = 0;
                }
                if ($this->QcDocument->save($this->request->data)) {
                    $this->Session->setFlash(__('The document has been saved.'));
                    if ($this->_show_approvals()) { // M9 correction 29: a failed approval request is undone and reported
                        $this->_m9_approval_begin();
                        if ($this->_save_approvals()) $this->_m9_approval_commit();
                        else $this->Session->setFlash(__('The document has been saved, but the approval request could not be sent.') . ($this->_m9_undo() ? '' : ' ' . __('Some changes could not be undone; this is recorded in the application log.')));
                    }
                } else {
                    $this->Session->setFlash(__('The document could not be saved. Please, try again.'));
                }
            } else {
                $this->Session->setFlash(__('The document could not be saved. Please, try again.'));
            }
        } else {
            $options = array('conditions' => array('QcDocument.' . $this->QcDocument->primaryKey => $id));
            $this->request->data = $this->QcDocument->find('first', $options);
        }
        $this->_commons();
    }
    /**
     * purge method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    public function purge($id = null) {
        $this->QcDocument->id = $id;
        if (!$this->QcDocument->exists()) {
            throw new NotFoundException(__('Invalid document'));
        }
        $this->request->onlyAllow('post', 'delete');
        if ($this->QcDocument->delete()) {
            $this->Session->setFlash(__('document deleted'));
            $this->redirect(array('action' => 'index'));
        }
        $this->Session->setFlash(__('document was not deleted'));
        $this->redirect(array('action' => 'index'));
    }
    /**
     * delete method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    public function delete($id = null, $parent_id = NULL) {
        if ($this->request->is('post')) {
            $this->loadModel('User');
            $user = $this->User->find('first', array('conditions' => array('User.status' => 1, 'User.soft_delete' => 0, 'User.publish' => 1, 'User.username' => $this->Session->read('User.username'))));
            if ($user) {
                if (!$this->_m9_password_matches($user['User']['password'], $this->request->data['QcDocument']['password'], $user['User']['id'])) {
                    $this->Session->setFlash(__('Incorrect password', true), 'default', array('class' => 'alert-danger'));
                } else {
                    $doc = $this->QcDocument->find('first',array(
                        'recursive'=>-1,
                        // 'fields'=>array('QcDocument.id','QcDocument.created_by'),
                        'conditions'=>array('QcDocument.id'=>$this->request->data['QcDocument']['id']
                    )));

                    $editors = json_decode($doc['QcDocument']['editors'],true);
                    if($editors != null){
                        if(!in_array($this->Session->read('User.id'), $editors)){
                            $this->Session->setFlash(__('You do not have permission to edit this document'));
                            $this->redirect(array('action' => 'index'));
                        }
                    }else{
                        $this->Session->setFlash(__('You do not have permission to edit this document'));
                        $this->redirect(array('action' => 'index'));
                    }
                    
                    // find all custom tables 
                    $id = $doc['QcDocument']['id'];
                    if($doc){
                        if($this->Session->read('User.id') == $doc['QcDocument']['created_by']){
                            if($id){
                                $this->QcDocument->delete($id, true);
                                $delFolder = new Folder(Configure::read('path') . DS . $id);
                                $delFolder->delete();
                            }
                            // delete archived documents
                            $archived = $this->QcDocument->find('all',array('conditions'=>array('QcDocument.parent_id'=>$id),'fields'=>array('QcDocument.id','QcDocument.parent_id')));
                            foreach($archived as $archive){
                                $this->QcDocument->delete($id, true);
                                $delFolder = new Folder(Configure::read('files') . DS . 'archive' . DS . $id);
                                $delFolder->delete();
                            }
                            $this->redirect(array('action' => 'index'));
                        }else{
                            $this->Session->setFlash(__('Document was not deleted as you are not the creator of the document'));
                            $this->redirect(array('action' => 'index'));
                        }
                    }else{
                        $this->Session->setFlash(__('Document does not exist'));
                        $this->redirect(array('action' => 'index'));
                    }
                }
            }
        }else{
            $this->set('id',$id);
            $this->set('parent_id',$parent_id);
            $customTables = $this->QcDocument->CustomTable->find('list',array('conditions'=>array('CustomTable.qc_document_id'=>$id)));
            $this->set('customTables',$customTables);
        }
    }

    public function delete_document() {
        if ($this->request->is('post')) {
            $this->loadModel('User');
            $user = $this->User->find('first', array('conditions' => array('User.status' => 1, 'User.soft_delete' => 0, 'User.publish' => 1, 'User.username' => $this->Session->read('User.username'))));
            if ($user) {
                if (!$this->_m9_password_matches($user['User']['password'], $this->request->data['QcDocument']['password'], $user['User']['id'])) {
                    $this->Session->setFlash(__('Incorrect password', true), 'default', array('class' => 'alert-danger'));
                } else {
                    $this->autoRender = false;
                    $file_path = base64_decode($this->request->data['QcDocument']['url']);
                    $path1 = explode(DS , $file_path);
                    $qcDocument = $this->QcDocument->find('first',array('conditions'=>array('QcDocument.id'=>$path1[count($path1)-2]),'recursive'=>-1));                    
                    if(!in_array($this->Session->read('User.id'),(array)json_decode($qcDocument['QcDocument']['editors'],true))){ // M9 correction 15: null editors list refused cleanly
                        $this->Session->setFlash(__('You are not authorized to delete this document'));
                        $this->redirect(array('action' => 'view',$qcDocument['QcDocument']['id'],'timestamp'=>date('Ymdhis')));
                    }                    
                    if($qcDocument){
                        $folder = Configure::read('path') . DS . $path1[count($path1)-2];
                        if($path1){
                            $delFolder = new Folder($folder);
                            $delFolder->delete();    
                        }                                                
                        $qcDocument['QcDocument']['file_key'] = '';
                        $qcDocument['QcDocument']['version'] = '';
                        $qcDocument['QcDocument']['versions'] = '';
                        $qcDocument['QcDocument']['version_keys'] = '';
                        $qcDocument['QcDocument']['file_status'] = 0;
                        $qcDocument['QcDocument']['file_type'] = '';
                        $qcDocument['QcDocument']['last_saved'] = '';
                        $this->QcDocument->create();
                        $this->QcDocument->save($qcDocument,false);
                        // must also change custome_table_document for tables linked with this document
                        $customTables = $this->QcDocument->CustomTable->find('all',array('recursive'=>-1,
                            // 'fields'=>array('CustomTable.id','CustomTable.file_key','CustomTable.version_keys','CustomTable.file_status'),
                            'conditions'=>array('CustomTable.qc_document_id'=>$qcDocument['QcDocument']['id'])));
                        if($customTables){
                            foreach($customTables as $customTable){
                                $this->QcDocument->CustomTable->create();
                                $customTable['CustomTable']['file_key'] = '';
                                $customTable['CustomTable']['version_keys'] = '';
                                $this->QcDocument->CustomTable->save($customTable,false);
                                // delete old file
                                $customTableFolder = Configure::read('path') . DS . 'custome_tables' . DS . $customTable['CustomTable']['id'];
                                if($customTable['CustomTable']['id']){
                                    $delFolder = new Folder($customTableFolder);
                                    $delFolder->delete();    
                                }
                            }
                        }
                    }
                    $this->Session->setFlash(__('File Deleted', true), 'default', array('class' => 'alert-success'));
                    $this->redirect($this->request->data['QcDocument']['ref']);
                }
            }
        } else {
            $this->set('ref', $this->referer());
        }
    }

    public function check_duplicates($field = null, $val = null, $id = null) {
        $this->autoRender = false;
        $field = $this->request->params['named']['field'];
        $val = $this->request->params['named']['value'];
        if ($field == 't') {
            $rec = $this->QcDocument->find('count', array('conditions' => array('QcDocument.title' => $val)));            
            if ($rec > 0) {
                return 1;
            } else {
                return 0;
            }
        }
        if ($field == 'n') {
            if(strlen($val) > 7){
                return 2;
            }
            $rec = $this->QcDocument->find('count', array('conditions' => array('QcDocument.document_number' => $val)));            
            if ($rec > 0) {
                return 1;
            } else {
                return 0;
            }
        }
    }
    public function clean_document_number($value = null) {
        $this->autoRender = false;        
        return preg_replace('/[^a-zA-Z0-9]/', '-', $this->request->params->name['document_number']);
        return $value;
    }

    public function load_blank_file($type = null){
        $filetype  = $this->request->params['named']['type'];        
        $to = WWW_ROOT . DS . 'files' . DS . $this->Session->read('User.company_id') . DS . 'qc_documents' .DS . 'tmp' . DS . $this->Session->read('User.id');
        if(!file_exists($to)){
            $testfolder = new Folder($to);
            $testfolder->create($to);
            $this->_m9_mode($to);
        }
        $from = WWW_ROOT . 'files' . DS . 'samples' .DS . 'sample.'.$filetype;        
        $to = $to . DS . 'blank.'.$filetype;        
        if(file_exists($from)){
            $fromFile = new File($from);
            $fromFile->copy($to,true);
            if(copy($from,$to)){
                $file = 'blank.'.$filetype;
                $url = Router::url('/', true) . 'files/'.$this->Session->read('User.company_id').'/qc_documents/tmp/'.$this->Session->read('User.id').'/blank.'.$filetype;
                $filekey = $this->_generate_onlyoffice_key($to);
                $this->set(array('file'=>$file,'url'=>$url,'filekey'=>$filekey,'filetype'=>$filetype));
            }else{
                echo "Unable to copy file";
            }
        }else{
            echo "File does not exists";
        }
    }

    public function update_access(){
        $this->autoRender = false;
        if ($this->request->is('post') || $this->request->is('put')) {
            $qcDocuments = $this->QcDocument->find('all',array(
                'recursive'=>-1,
                'conditions'=>array(
                    'OR'=>array(
                        'QcDocument.id'=>$this->request->data['qc_document_id'],
                        'QcDocument.parent_document_id'=>$this->request->data['qc_document_id']
                        )                    
                    )
            ));

            if($this->request->data['typ'] == 0){
                $action = 'remove'.$this->request->data['action'];
            }else{
                $action = 'add'.$this->request->data['action'];
            }
            
            if($qcDocuments){
                foreach($qcDocuments as $qcDocument){
                    switch ($action) {
                        case 'addview' :
                            $users = json_decode($qcDocument['QcDocument']['user_id'],true);
                            $users[] = $this->request->data['user_id'];
                            $qcDocument['QcDocument']['user_id'] = json_encode($users);
                        break;

                        case 'removeview' :
                            $qcDocument['QcDocument']['user_id'] = str_replace($this->request->data['user_id'], '', $qcDocument['QcDocument']['user_id']);
                            $users = json_decode($qcDocument['QcDocument']['user_id'],true);
                            foreach($users as $user){
                                if($user)$newView[] = $user;
                            }
                            $qcDocument['QcDocument']['user_id'] = json_encode($newView);
                        break;

                        case 'addedit' :
                            $users = json_decode($qcDocument['QcDocument']['editors'],true);
                            $users[] = $this->request->data['user_id'];
                            $qcDocument['QcDocument']['editors'] = json_encode($users);
                        break;

                        case 'removeedit' :
                            $qcDocument['QcDocument']['editors'] = str_replace($this->request->data['user_id'], '', $qcDocument['QcDocument']['editors']);
                            $users = json_decode($qcDocument['QcDocument']['editors'],true);
                            foreach($users as $user){
                                if($user)$newEdit[] = $user;
                            }
                            $qcDocument['QcDocument']['editors'] = json_encode($newEdit);
                        break;
                    }
                    
                    $this->QcDocument->create();
                    if($this->QcDocument->save($qcDocument,false)){
                        // remove copy_acl_from
                        $this->loadModel('User');
                        $user = $this->User->find('first',array('recursive'=>-1,'conditions'=>array('User.id'=>$this->request->data['user_id'])));
                        if($user){
                            $user['User']['copy_acl_from'] = NULL;
                            $this->User->create();
                            $this->User->save($user,false);
                        }
                        // return true;
                    }else{
                        // return false;
                    }
                }
                return true;
            }else{
                return false;
            }
        }

        // if($qcDocument){
        //     if($this->request->data['action'] == 'addview'){
        //         $users = json_decode($qcDocument['QcDocument']['user_id'],true);
        //         $users[] = $this->request->data['user_id'];
        //         $qcDocument['QcDocument']['user_id'] = json_encode($users);
        //     }
        //     if($this->request->data['action'] == 'removeview'){
        //         $qcDocument['QcDocument']['user_id'] = str_replace($this->request->data['user_id'], '', $qcDocument['QcDocument']['user_id']);
        //         $users = json_decode($qcDocument['QcDocument']['user_id'],true);
        //         foreach($users as $user){
        //             if($user)$newView[] = $user;
        //         }
        //         $qcDocument['QcDocument']['user_id'] = json_encode($newView);
        //     }
        //     if($this->request->data['action'] == 'removeedit'){
        //         $qcDocument['QcDocument']['editors'] = str_replace($this->request->data['user_id'], '', $qcDocument['QcDocument']['editors']);
        //         $users = json_decode($qcDocument['QcDocument']['editors'],true);
        //         foreach($users as $user){
        //             if($user)$newEdit[] = $user;
        //         }
        //         $qcDocument['QcDocument']['editors'] = json_encode($newEdit);
        //     }
        //     if($this->request->data['action'] == 'addedit'){
        //         $users = json_decode($qcDocument['QcDocument']['editors'],true);
        //         $users[] = $this->request->data['user_id'];
        //         $qcDocument['QcDocument']['editors'] = json_encode($users);
        //     }
        //     $this->QcDocument->create();
        //     if($this->QcDocument->save($qcDocument,false)){
        //         return true;
        //     }else{
        //         return false;
        //     }
        // }else{
        //     return false;
        // }
    }

    public function reloaddocument($docid = null,$action = null){
        $docid = $this->request->params['pass'][0];
        $action = $this->request->params['pass'][1];
        if($docid){
            $qcDocument = $this->QcDocument->find('first',array('conditions'=>array('QcDocument.id'=>$docid)));
            $this->set('qcDocument',$qcDocument);
            $this->set('docid',$docid);
        }
        if($action){
            $this->set('action',$action);
        }
    }

    public function _double_check($docs = null){
        $number = "QMS-".str_pad($docs, 3, 0, STR_PAD_LEFT);
        $rec = $this->QcDocument->find('count', array('conditions' => array('QcDocument.document_number' => $number)));
        if($rec == 0){
            return $number;
        }else{
            $docs = $docs + 1;
            return $this->_double_check($docs);
        }
    }

    public function _get_last_number(){
        $docs = $this->QcDocument->find('count',array());
        $docs = $docs + 1;
        $new_document_number = $this->_double_check($docs);
        return $new_document_number;
    }

    // change history functionality
    public function define_change_history_table(){
        $this->loadModel('Company');
        $customTables = $this->QcDocument->CustomTable->find('list');
        $this->set('customTables',$customTables);
        if($this->request->params['named']['custom_table_id']){
            $customTable = $this->QcDocument->CustomTable->find('first',array('conditions'=>array('CustomTable.id'=>$this->request->params['named']['custom_table_id']),
                'fields'=>array('CustomTable.id','CustomTable.fields'),
                'recursive'=>-1));
            $this->set('customTable',$customTable);
        }else{
            if ($this->request->is('post') || $this->request->is('put')) {
                $company = $this->Company->find('first',array('conditions'=>array('Company.id'=>$this->Session->read('User.company_id'))));
                if($company){
                    $company['Company']['change_management_table'] = $this->request->data['QcDocument']['custom_table_id'];
                    $company['Company']['change_management_table_fields'] = json_encode($this->request->data['QcDocument']['fields']);
                    $this->Company->create();
                    $this->Company->save($company,false);
                    $this->Session->setFlash(__('Form linked.'));
                    $this->redirect(array('action' => 'index','timestamp'=>date('ymdhis')));
                }
            }else{
                $company = $this->Company->find('first',array('fields'=>array(
                    'Company.id',
                    'Company.change_management_table',
                    'Company.change_management_table_fields',
                ), 'conditions'=>array('Company.id'=>$this->Session->read('User.company_id'))));
                if($company['Company']['change_management_table']){
                    $customTable = $this->QcDocument->CustomTable->find('first',array('conditions'=>array('CustomTable.id'=>$company['Company']['change_management_table']),
                        'fields'=>array('CustomTable.id','CustomTable.fields'),
                        'recursive'=>-1));
                }
                $this->set('customTable',$customTable);
                $this->set('company',$company);
            }
        }
    }

    public function change_history($id = null){
        if(!$id)$id = $this->request->params['named']['id'];
        else $this->request->params['named']['id'] = $id;
        $this->loadModel('Company');
        $company = $this->Company->find('first',array('conditions'=>array('Company.id'=>$this->Session->read('User.company_id')),'fields'=>array('Company.id','Company.change_management_table','Company.change_management_table_fields')));
        if($company['Company']['change_management_table']){
            $fields = json_decode($company['Company']['change_management_table_fields'],true);
            $this->set('fields',$fields);
            $customTable = $this->QcDocument->CustomTable->find('first',array(
                'conditions'=>array('CustomTable.id'=>$company['Company']['change_management_table']),
                'fields'=>array(
                    'CustomTable.id',
                    'CustomTable.name',
                    'CustomTable.table_name',
                    'CustomTable.fields',
                    'CustomTable.belongs_to',
                ),
                'recursive'=>-1
            ));

            if($customTable){
                // load model
                $model = Inflector::Classify($customTable['CustomTable']['table_name']);
                $this->loadModel($model);
                $records = $this->$model->find('all',array('conditions'=>array($model.'.document_for_change'=>$this->request->params['named']['id'])));
                $this->set('records',$records);
                $this->set('table',$customTable['CustomTable']['name']);
                $this->set('table_name',$customTable['CustomTable']['table_name']);
                $this->set('model',$model);
                $this->set('table_fields',$customTable['CustomTable']['fields']);
                $this->set('belogsTos',$customTable['CustomTable']['belongs_to']);
                $this->set('prompt',false);
                return count($records);
            }
        }else{
            $this->set('prompt',true);
        }
    }

    public function update_revision($id = null){
        if ($this->request->is('post') || $this->request->is('put')) {
            // M9 correction 22 (owner review 30 Oct 2026, findings 1, 3 and 4). Everything is checked before anything changes:
            // the revision number and date, the change request and replacement file (rebuilt on the server, the posted values
            // must match them), and both files on disk. The current file path is built on the server; the posted replace_file
            // is no longer used. Files are copied and verified first, then the two records are saved, then the document folder
            // is swapped. qc_documents is MyISAM (no transaction rollback), so a failure after a save is undone explicitly:
            // the archive row is deleted and the active row is saved back with its original values; files are put back.
            $id = (isset($this->request->data['QcDocument']['id']) && is_scalar($this->request->data['QcDocument']['id'])) ? trim((string)$this->request->data['QcDocument']['id']) : '';
            $back = array('action' => 'update_revision', $id, 'timestamp' => date('Ymdhis'));
            $stop = function ($msg) use ($back) { $this->Session->setFlash($msg); $this->redirect($back); };
            $qcDocument = ($id === '') ? array() : $this->QcDocument->find('first', array('conditions' => array('QcDocument.id' => $id), 'recursive' => -1));
            if (empty($qcDocument) || $qcDocument['QcDocument']['archived'] == 1) {
                $this->Session->setFlash(__('Document not found or already archived.'));
                $this->redirect(array('action' => 'index'));
            }
            $oldRev = trim((string)$qcDocument['QcDocument']['revision_number']);
            $newRev = isset($this->request->data['QcDocument']['revision_number']) ? $this->request->data['QcDocument']['revision_number'] : null;
            $why = $this->_m9_revision_check($newRev, $id, true);
            if ($why !== '') $stop($why);
            $newRev = trim((string)$newRev);
            $revDate = (isset($this->request->data['QcDocument']['revision_date']) && is_scalar($this->request->data['QcDocument']['revision_date'])) ? trim((string)$this->request->data['QcDocument']['revision_date']) : '';
            $d = DateTime::createFromFormat('Y-m-d', $revDate);
            if (false === $d || $d->format('Y-m-d') !== $revDate) $stop(__('Revision date must be a valid date (YYYY-MM-DD).'));
            $src = $this->_m9_revision_sources($id);
            if (is_string($src)) { $this->_m9_ac_log('revision_refused', $src); $stop(__('No change request with a replacement file was found for this document. Nothing was changed.')); }
            foreach (array('cr_id' => $src['cr_id'], 'new_file_id' => $src['file_id']) as $f => $want) {
                $got = (isset($this->request->data['QcDocument'][$f]) && is_scalar($this->request->data['QcDocument'][$f])) ? trim((string)$this->request->data['QcDocument'][$f]) : '';
                if ($got !== (string)$want) $this->_m9_ac_refuse('revision_source_mismatch', $f);
            }
            $apr = $this->_m9_cr_approved($src['model'], $src['cr_id']); // M9 correction 22d (owner, 6 Nov 2026)
            if ($apr !== '') { $this->_m9_ac_log('revision_refused', 'cr_not_approved:' . $apr); $stop(__('The change request for this document has not been formally approved. Nothing was changed.')); }
            $type = strtolower((string)$qcDocument['QcDocument']['file_type']);
            if (preg_match('/^[a-z0-9]{1,6}$/', $type) !== 1) $stop(__('The document has no valid file type. Nothing was changed.'));
            $base = realpath(Configure::read('path'));
            $activeDir = Configure::read('path') . DS . $id;
            $current = $activeDir . DS . $this->_clean_table_names($qcDocument['QcDocument']['document_number'] . '-' . $qcDocument['QcDocument']['title'] . '-' . $oldRev) . '.' . $type;
            if (false === is_file($current)) {
                $c = array(); foreach ((array)glob($activeDir . DS . '*.' . $type) as $g) if (is_file($g)) $c[] = $g;
                $current = (count($c) === 1) ? $c[0] : '';
            }
            $rc = ($current === '') ? false : realpath($current);
            if (false === $base || false === $rc || strpos($rc, $base . DS . $id . DS) !== 0 || false === is_readable($rc)) {
                $this->_m9_ac_log('revision_refused', 'current_file');
                $stop(__('The current document file could not be found. Nothing was changed.'));
            }
            $this->loadModel('DownloadFile');
            $nf = $this->DownloadFile->find('first', array('recursive' => -1, 'conditions' => array('DownloadFile.id' => $src['file_id'])));
            $filesBase = realpath(Configure::read('files'));
            $newPath = $nf ? Configure::read('files') . DS . 'files' . DS . $nf['DownloadFile']['id'] . DS . $nf['DownloadFile']['name'] . '.' . $nf['DownloadFile']['file_type'] : '';
            $rn = ($newPath === '') ? false : realpath($newPath);
            if (false === $filesBase || false === $rn || strpos($rn, $filesBase . DS . 'files' . DS) !== 0 || false === is_file($rn) || false === is_readable($rn)) {
                $this->_m9_ac_log('revision_refused', 'replacement_file');
                $stop(__('The replacement file could not be found. Nothing was changed.'));
            }
            $ts = date('YmdHis');
            $archiveDir = Configure::read('files') . DS . 'archive' . DS . $id . DS . $oldRev . DS . $src['cr_id'];
            $archiveFile = $archiveDir . DS . 'archived.' . $type;
            $newName = $this->_clean_table_names($qcDocument['QcDocument']['document_number'] . '-' . $qcDocument['QcDocument']['title'] . '-' . $newRev) . '.' . $type;
            $stage = $activeDir . '.m9new_' . $ts;
            $aside = $activeDir . '.m9old_' . $ts;
            if (file_exists($archiveFile)) $stop(__('This revision is already archived for this change request. Nothing was changed.'));
            $clean = function () use ($archiveFile, $stage) {
                if (is_file($archiveFile)) @unlink($archiveFile);
                if (is_dir($stage)) { $f = new Folder(); $f->delete($stage); }
            };
            // 1. archive copy of the current file, verified
            $mk = new Folder();
            if (false === is_dir($archiveDir) && false === $mk->create($archiveDir, 0755)) $stop(__('The archive folder could not be created. Nothing was changed.'));
            if (false === @copy($rc, $archiveFile) || hash_file('sha256', $rc) !== hash_file('sha256', $archiveFile)) { $clean(); $stop(__('The current document could not be archived. Nothing was changed.')); }
            // 2. replacement file staged next to the document folder, verified
            if (false === $mk->create($stage, 0755) || false === @copy($rn, $stage . DS . $newName) || hash_file('sha256', $rn) !== hash_file('sha256', $stage . DS . $newName)) { $clean(); $stop(__('The replacement file could not be prepared. Nothing was changed.')); }
            // 3. both records (a transaction where the engine supports it; undone explicitly below in any case)
            $original = $qcDocument;
            $archiveQcDocument = $qcDocument;
            unset($archiveQcDocument['QcDocument']['id'], $archiveQcDocument['QcDocument']['sr_no']);
            $archiveQcDocument['QcDocument']['revision_number'] = $oldRev;
            $archiveQcDocument['QcDocument']['publish'] = 0;
            $archiveQcDocument['QcDocument']['archived'] = 1;
            $archiveQcDocument['QcDocument']['cr_id'] = $src['cr_id'];
            $archiveQcDocument['QcDocument']['parent_id'] = $id;
            $archiveQcDocument['QcDocument']['modified'] = date('Y-m-d H:i:s');
            $archiveQcDocument['QcDocument']['modified_by'] = $this->Session->read('User.id');
            $archiveQcDocument['QcDocument']['file_key'] = $this->_generate_onlyoffice_key($id . 'a' . $ts);
            $archiveQcDocument['QcDocument']['document_status'] = 4;
            $qcDocument['QcDocument']['revision_number'] = $newRev;
            $qcDocument['QcDocument']['revision_date'] = $revDate;
            $qcDocument['QcDocument']['publish'] = 1;
            $qcDocument['QcDocument']['document_status'] = 5;
            $qcDocument['QcDocument']['modified'] = date('Y-m-d H:i:s');
            $qcDocument['QcDocument']['modified_by'] = $this->Session->read('User.id');
            $qcDocument['QcDocument']['file_key'] = $this->_generate_onlyoffice_key($id . 'c' . $ts);
            $ds = $this->QcDocument->getDataSource();
            $ds->begin();
            $archiveId = null; $activeSaved = false;
            $this->QcDocument->create();
            $ok = (bool)$this->QcDocument->save($archiveQcDocument, false);
            if ($ok) $archiveId = $this->QcDocument->id;
            if ($ok) { $this->QcDocument->create(); $ok = (bool)$this->QcDocument->save($qcDocument, false); $activeSaved = $ok; }
            // 4. swap the document folder: current folder aside, staged folder in its place
            $moved = false;
            if ($ok) {
                $moved = @rename($activeDir, $aside);
                $ok = $moved && @rename($stage, $activeDir);
            }
            if ($ok) $ok = (bool)$ds->commit();
            if (false === $ok) {
                if ($moved) { if (is_dir($activeDir) && false === is_dir($stage)) @rename($activeDir, $stage); @rename($aside, $activeDir); }
                $ds->rollback();
                if ($activeSaved) { // put every column back exactly (updateAll: no callbacks, no automatic modified date)
                    $dbs = $this->QcDocument->getDataSource(); $set = array();
                    foreach ($original['QcDocument'] as $k => $v) if ($k !== 'id' && $k !== 'sr_no') $set['QcDocument.' . $k] = $dbs->value($v);
                    $this->QcDocument->updateAll($set, array('QcDocument.id' => $id));
                }
                if ($archiveId) $this->QcDocument->deleteAll(array('QcDocument.id' => $archiveId), false, false);
                $clean();
                $this->_m9_ac_log('revision_failed_restored', substr($id, 0, 8));
                $stop(__('The revision could not be saved. The document, its file and its records are unchanged.'));
            }
            $f = new Folder(); $f->delete($aside);
            $this->_m9_ac_log('revision_saved', substr($id, 0, 8) . ' ' . $oldRev . '>' . $newRev);
            $this->Session->setFlash(__('Change request successfully completed. Document successfully updated.'));
            $this->redirect(array('action' => 'view', $id));
        }else{
            // get CR table
            $this->loadModel('Company');
            $company = $this->Company->find('first',array('conditions'=>array('Company.id'=>$this->Session->read('User.company_id')),'fields'=>array('Company.id','Company.change_management_table','Company.change_management_table_fields')));
            if($company['Company']['change_management_table']){
                $fields = json_decode($company['Company']['change_management_table_fields'],true);
                $this->set('fields',$fields);
                $customTable = $this->QcDocument->CustomTable->find('first',array(
                    'conditions'=>array('CustomTable.id'=>$company['Company']['change_management_table']),
                    'fields'=>array(
                        'CustomTable.id',
                        'CustomTable.name',
                        'CustomTable.table_name',
                        'CustomTable.fields',
                        'CustomTable.belongs_to',                    
                    ),
                    'recursive'=>-1
                ));                
                if($customTable){
                    // load model
                    $model = Inflector::Classify($customTable['CustomTable']['table_name']);
                    $this->loadModel($model);
                    $records = $this->$model->find('all',array(
                        'limit'=>1,
                        'order'=>array($model.'.sr_no'=>'DESC'),
                        'conditions'=>array($model.'.document_for_change'=>$id)));
                    $this->set('records',$records);
                    $this->set('table',$customTable['CustomTable']['name']);
                    $this->set('table_name',$customTable['CustomTable']['table_name']);
                    $this->set('model',$model);
                    $this->set('table_fields',$customTable['CustomTable']['fields']);
                    $this->set('belogsTos',$customTable['CustomTable']['belongs_to']);
                    $this->set('prompt',false);
                    /// also load existing document
                    $qcDocument = $this->QcDocument->find('first',array('conditions'=>array('QcDocument.id'=>$id)));
                    $this->set('document',$qcDocument);
                    $this->set('qcDocument',$qcDocument);                    
                    if($qcDocument['QcDocument']['archived'] == true){
                        $this->Session->setFlash(__('Document is already archived.'));
                        $this->redirect(array('action' => 'view', $qcDocument['QcDocument']['parent_id'], 'timestamp'=>date('ymdhis')));
                    }                    
                    // find changed document
                    $additionalFiles = json_decode($records[0][$model]['additional_files'],true);                    
                    $this->loadModel('File');
                    foreach($additionalFiles as $additionalFile){
                        $file = $this->File->find('first',array('conditions'=>array(
                            'File.id'=>$additionalFile)));
                        $this->set('fileEdit',$file);
                    }
                }
            }else{
                $this->set('prompt',true);
            }
        }
    }

    // M9 correction 22: server-side check of a revision number on every path that saves one (add, add_bulk, edit, rename,
    // update_revision). A revision is a whole number from 0 to 9999. It may not be below the highest archived revision of the
    // document; a new revision (update_revision) must be above the current revision and every archived one.
    // Returns '' when the value is accepted, otherwise the message to show.
    public function _m9_revision_check($v, $id = null, $newRevision = false) {
        if (false === is_scalar($v) || preg_match('/^[0-9]{1,4}$/', trim((string)$v)) !== 1) return __('Revision number must be a whole number from 0 to 9999.');
        if ($id === null || false === is_scalar($id) || (string)$id === '') return '';
        $v = (int)trim((string)$v);
        $a = $this->QcDocument->find('first', array('recursive' => -1, 'fields' => array('MAX(CAST(QcDocument.revision_number AS SIGNED)) AS m'), 'conditions' => array('QcDocument.parent_id' => $id, 'QcDocument.archived' => 1)));
        $m = (isset($a[0]['m']) && $a[0]['m'] !== null) ? (int)$a[0]['m'] : null;
        if ($newRevision) {
            $cur = (int)$this->QcDocument->field('revision_number', array('QcDocument.id' => $id));
            if ($v <= $cur) return __('The new revision number must be higher than the current revision (%s).', $cur);
            if ($m !== null && $v <= $m) return __('The new revision number must be higher than every archived revision (%s).', $m);
        } elseif ($m !== null && $v < $m) {
            return __('Revision number may not be lower than the highest archived revision (%s).', $m);
        }
        return '';
    }

    // M9 correction 22: the change request and replacement file for a revision, found on the server exactly as the
    // update_revision page finds them (latest record of the change management form for this document, last attached file).
    // Returns array('cr_id', 'file_id') or a reason.
    public function _m9_revision_sources($id) {
        $this->loadModel('Company');
        $company = $this->Company->find('first', array('recursive' => -1, 'conditions' => array('Company.id' => $this->Session->read('User.company_id')), 'fields' => array('Company.id', 'Company.change_management_table')));
        if (empty($company['Company']['change_management_table'])) return 'no_change_table';
        $ct = $this->QcDocument->CustomTable->find('first', array('recursive' => -1, 'conditions' => array('CustomTable.id' => $company['Company']['change_management_table']), 'fields' => array('CustomTable.id', 'CustomTable.table_name')));
        if (empty($ct)) return 'change_table_unknown';
        $model = Inflector::Classify($ct['CustomTable']['table_name']);
        $this->loadModel($model);
        $rec = $this->$model->find('first', array('recursive' => -1, 'order' => array($model . '.sr_no' => 'DESC'), 'conditions' => array($model . '.document_for_change' => $id)));
        if (empty($rec[$model]['id'])) return 'no_change_request';
        $files = json_decode((string)$rec[$model]['additional_files'], true);
        if (false === is_array($files)) return 'no_replacement_file';
        $this->loadModel('File');
        $fid = '';
        foreach ($files as $x) {
            if (false === is_scalar($x)) continue;
            $f = $this->File->find('first', array('recursive' => -1, 'conditions' => array('File.id' => $x), 'fields' => array('File.id')));
            if ($f) $fid = (string)$f['File']['id'];
        }
        if ($fid === '') return 'no_replacement_file';
        return array('cr_id' => (string)$rec[$model]['id'], 'file_id' => $fid, 'model' => $model);
    }

    // M9 correction 22d (owner, 6 Nov 2026): edit and rename may not change the revision number; only update_revision does.
    // The posted value must equal the stored value exactly; a missing value is accepted on rename (the stored one is kept).
    // Returns '' when accepted, otherwise the message to show.
    public function _m9_revision_unchanged($v, $id, $missingOk = false) {
        $cur = $this->QcDocument->field('revision_number', array('QcDocument.id' => $id));
        if ($v === null && $missingOk) return '';
        if (is_scalar($v) && trim((string)$v) === trim((string)$cur)) return '';
        return __('The revision number cannot be changed here (current revision %s). Revisions are made only through Update Revision with an approved change request.', $cur);
    }

    // M9 correction 22d (owner, 6 Nov 2026): a revision needs a formally approved change request. Checked from stored data
    // only, never from posted values: the request exists, is not deleted, is published and has an approver; it has approval
    // requests, every request of its latest approval cycle is closed (status 1) and was addressed to someone other than the
    // request's creator; no response says Not Approved; and the request was not changed after its final approval.
    // Returns '' when approved, otherwise the reason (logged, never shown).
    public function _m9_cr_approved($model, $crId) {
        if (false === is_string($model) || $model === '' || false === is_scalar($crId) || (string)$crId === '') return 'no_request';
        $this->loadModel($model);
        $cr = $this->$model->find('first', array('recursive' => -1, 'conditions' => array($model . '.id' => (string)$crId)));
        if (empty($cr[$model]['id'])) return 'no_request';
        $r = $cr[$model];
        if (isset($r['soft_delete']) && (int)$r['soft_delete'] !== 0) return 'deleted';
        if (false === isset($r['publish']) || (int)$r['publish'] !== 1) return 'not_published';
        if (empty($r['approved_by'])) return 'no_approver';
        $this->loadModel('Approval');
        $aps = array();
        foreach ($this->Approval->find('all', array('recursive' => -1, 'conditions' => array('Approval.model_name' => $model, 'Approval.record' => (string)$crId))) as $a) {
            if (isset($a['Approval']['soft_delete']) && (int)$a['Approval']['soft_delete'] === 1) continue;
            $aps[] = $a['Approval'];
        }
        if (empty($aps)) return 'no_approval';
        $cycle = max(array_map(function ($a) { return (int)$a['approval_cycle']; }, $aps));
        $ids = array(); $last = '';
        foreach ($aps as $a) {
            if ((int)$a['approval_cycle'] !== $cycle) continue;
            if ((string)$a['status'] !== '1') return 'approval_open';
            if ((string)$a['user_id'] === '' || (string)$a['user_id'] === (string)$r['created_by']) return 'self_approval';
            $ids[] = (string)$a['id'];
            if ((string)$a['modified'] > $last) $last = (string)$a['modified'];
        }
        $this->loadModel('ApprovalComment');
        if ($this->ApprovalComment->find('count', array('recursive' => -1, 'conditions' => array('ApprovalComment.approval_id' => $ids, 'ApprovalComment.user_id' => 'Not Approved'))) > 0) return 'not_approved';
        if ($last === '' || strtotime((string)$r['modified']) > strtotime($last) + 5) return 'changed_after_approval';
        return '';
    }

    public function load_revisions($id = null){
        $qcDocuments = $this->QcDocument->find('all',array('conditions'=>array('QcDocument.parent_id'=>$this->request->params['named']['id'])));
        $this->set('qcDocuments',$qcDocuments);
    }

    public function _add_process($data = null,$id = null){
        $data['Process']['name'] = $data['QcDocument']['title'];
        $data['Process']['qc_document_id'] = $id;
        $data['Process']['applicable_to_branches'] = json_encode(array($data['QcDocument']['share_with_branches']));
        $data['Process']['process_owners'] = json_encode(array($data['QcDocument']['share_with_departments']));
        $data['Process']['standards'] = json_encode(array($data['QcDocument']['standard_id']));
        $data['Process']['clauses'] = json_encode(array($data['QcDocument']['clause_id']));
        $data['Process']['schedule_id'] = $data['QcDocument']['schedule_id'];
        $data['Process']['prepared_by'] = $data['QcDocument']['prepared_by'];
        $data['Process']['approved_by'] = $data['QcDocument']['approved_by'];
        $data['Process']['created'] = $data['QcDocument']['created'];
        $data['Process']['created_by'] = $data['QcDocument']['created_by'];
        $data['Process']['modified'] = $data['QcDocument']['modified'];
        $data['Process']['modified_by'] = $data['QcDocument']['modified_by'];
        $this->loadModel('Process');
        $this->Process->save($data['Process'],false);
        return true;
    }

    public function _update_process($data = null,$id = null){
        $this->loadModel('Process');
        // M9 correction 25 (owner, 6 Nov 2026): $processes was never defined, so linked processes were never updated.
        $processes = (is_scalar($id) && (string)$id !== '') ? $this->Process->find('all', array('recursive' => -1, 'conditions' => array('Process.qc_document_id' => (string)$id))) : array();
        $m9done = array(); // M9 correction 27: originals of the processes already saved, to put back on a failure
        if($processes){
            foreach($processes as $process){
                $m9orig = $process['Process'];
                $process['Process']['name'] = $data['QcDocument']['title'];
                $process['Process']['qc_document_id'] = $id;
                $process['Process']['applicable_to_branches'] = json_encode($data['QcDocument']['share_with_branches']);
                $process['Process']['process_owners'] = json_encode($data['QcDocument']['share_with_departments']);
                $process['Process']['standards'] = json_encode(array($data['QcDocument']['standard_id']));
                $process['Process']['clauses'] = json_encode(array($data['QcDocument']['clause_id']));
                $process['Process']['schedule_id'] = $data['QcDocument']['schedule_id'];
                $process['Process']['prepared_by'] = $data['QcDocument']['prepared_by'];
                $process['Process']['approved_by'] = $data['QcDocument']['approved_by'];
                $this->Process->create();
                try { $m9ok = (bool)$this->Process->save($process['Process'],false); } catch (Exception $m9e) { $m9ok = false; } // M9 correction 27: every save checked (a database error throws)
                if (false === $m9ok) {
                    $m9bad = 'process_save_failed:' . substr((string)$m9orig['id'], 0, 8);
                    $m9done[] = $m9orig;
                    foreach (array_reverse($m9done) as $m9o) if (false === $this->_m9_put_back('Process', $m9o)) $m9bad .= ' process_restore_failed:' . substr((string)$m9o['id'], 0, 8) . ':' . $this->m9PutBackDiff;
                    return $m9bad;
                }
                $m9done[] = $m9orig;
            }
        }
        return true;
    }

    // M9 correction 30 (owner review 9 Nov 2026): _m9_put_back is now in AppController (NULL-aware comparison).

    public function add_bulk(){
        if ($this->request->is('post') || $this->request->is('put')) {
            foreach ((array)$this->request->data['Docs'] as $m9doc) { // M9 correction 22: every row is checked before any is saved
                $m9why = $this->_m9_revision_check(isset($m9doc['QcDocument']['revision_number']) ? $m9doc['QcDocument']['revision_number'] : null);
                if ($m9why !== '') { $this->Session->setFlash($m9why); $this->redirect(array('action' => 'add_bulk')); }
            }
            foreach($this->request->data['Docs'] as $qcDocument){
                $qcDocument['QcDocument']['name'] = ltrim(rtrim($qcDocument['QcDocument']['title']));
                $qcDocument['QcDocument']['title'] =ltrim(rtrim($this->_clean_table_names($qcDocument['QcDocument']['title'])));
                $qcDocument['QcDocument']['document_number'] = ltrim(rtrim($qcDocument['QcDocument']['document_number']));
                $qcDocument['QcDocument']['revision_number'] = ltrim(rtrim($qcDocument['QcDocument']['revision_number']));
                $qcDocument['QcDocument']['standard_id'] = $this->request->data['QcDocument']['standard_id'];
                $qcDocument['QcDocument']['clause_id'] = $this->request->data['QcDocument']['clause_id'];
                $qcDocument['QcDocument']['qc_document_category_id'] = $this->request->data['QcDocument']['qc_document_category_id'];
                $qcDocument['QcDocument']['branches'] = json_encode(array_keys($this->_get_branch_list()));
                $qcDocument['QcDocument']['departments'] = json_encode(array_keys($this->_get_department_list()));
                $qcDocument['QcDocument']['designations'] = json_encode(array_keys($this->_get_designation_list()));
                $qcDocument['QcDocument']['user_id'] = json_encode(array_keys($this->_get_user_list()));
                $qcDocument['QcDocument']['editors'] = json_encode(array_keys($this->_get_user_list()));
                $qcDocument['QcDocument']['file_type'] = 'docx';
                $qcDocument['QcDocument']['data_type'] = 2;
                $qcDocument['QcDocument']['it_categories'] = 0;
                $qcDocument['QcDocument']['archived'] = 0;
                $qcDocument['QcDocument']['document_status'] = 1;
                $qcDocument['QcDocument']['add_records'] = 1;
                $qcDocument['QcDocument']['publish'] = 1;
                $qcDocument['QcDocument']['soft_delete'] = 0;
                $qcDocument['QcDocument']['parent_document_id'] = -1;
                $qcDocument['QcDocument']['undate_custom_table_document'] = 0;
                $qcDocument['QcDocument']['schedule_id'] = '56d1564b-0acc-48f6-9beb-03a7db1e6cf9';
                $qcDocument['QcDocument']['created'] = $qcDocument['QcDocument']['modified'] = date('Y-m-d H:i:s');
                $qcDocument['QcDocument']['issued_by'] = $qcDocument['QcDocument']['issuing_authority_id'] = $qcDocument['QcDocument']['approved_by'];
                $qcDocument['QcDocument']['created_by'] = $qcDocument['QcDocument']['modified_by'] = $this->Session->read('User.id');
                $qcDocument['QcDocument']['company_id'] =  $this->Session->read('User.company_id');
                $file_name = $qcDocument['QcDocument']['title'];
                $document_number = $qcDocument['QcDocument']['document_number'];
                $document_version = $qcDocument['QcDocument']['revision_number'];
                $file_name = $document_number . '-' . $file_name . '-' . $document_version;
                $file_name = $this->_clean_table_names($file_name);
                $file_name = $file_name. '.docx';
                $this->QcDocument->create();
                if($this->QcDocument->save($qcDocument['QcDocument'],false)){
                    $id = $this->QcDocument->id;
                    $from = WWW_ROOT  . 'files' . DS . 'samples' . DS . 'sample.docx';                    
                    $file = Configure::read('path') . DS . $id . DS . $file_name;               
                    $to = Configure::read('path') . DS . $id;
                    $history_file_for_save = $file;
                    if(!file_exists($to)){
                        $testfolder = new Folder($to);
                        $testfolder->create($to);
                        $this->_m9_mode($to);
                    }
                    $to = $file;
                    $fromFile = new File($from);
                    $fromFile->copy($to,true);
                    if(copy($from,$to)){
                        $key = $this->_generate_onlyoffice_key($qcDocument['QcDocument']['id'] . date('Ymdhis'));
                        $this->set('file_key', $key);
                        $qcdoc = $this->QcDocument->find('first', array('recursive' => - 1, 'conditions' => array('QcDocument.id' => $id)));
                        $qcdoc['QcDocument']['file_key'] = $key;
                        $qcdoc['QcDocument']['file_type'] = 'docx';
                        $qcdoc['QcDocument']['file_key'] = $key;
                        $this->QcDocument->create();
                        $this->QcDocument->save($qcdoc['QcDocument']);
                        $json = [
                            "created" => date("Y-m-d H:i:s"),
                            'uid'=>$this->Session->read('User.id'),
                            'name'=>$this->Session->read('User.name'),
                        ];
                        $version = 1;
                        $history_file_for_save = $history_file_for_save.'-hist' . DS . $version;
                        $historyFolder = new Folder($history_file_for_save);
                        $historyFolder->create($history_file_for_save);
                        $this->_m9_mode($history_file_for_save);
                        file_put_contents($history_file_for_save . DS . "createdInfo.json", json_encode($json, JSON_PRETTY_PRINT));
                    }else{

                    }
                }
            }
            $this->Session->setFlash(__('Document Added'));
            $this->redirect(array('action' => 'index'));  
        }
        $this->_commons();
        $this->set('documentTypes',$this->QcDocument->customArray['documentTypes']);
    }

    public function fix_names(){
        $qcDocuments = $this->QcDocument->find('all',array('conditions'=>array(),'recursive'=>-1,'fields'=>array('QcDocument.id','QcDocument.name','QcDocument.title','QcDocument.version','QcDocument.file_type','QcDocument.issue_number','QcDocument.document_number','QcDocument.revision_number')));
        foreach($qcDocuments as $qcDocument){
            $qcDocument['QcDocument']['name'] = Inflector::humanize($qcDocument['QcDocument']['title']);
            $qcDocument['QcDocument']['title'] = $this->_clean_table_names($qcDocument['QcDocument']['title']);
            $this->QcDocument->create();
            $this->QcDocument->save($qcDocument,false);
        }
        exit;
    }

    public function rename($id = null, $value = null){
        $value = base64_decode($value);
        // M9 correction 26 (6 Nov 2026): the rename form posts no id, so the document number check and the redirects use the address id
        if ($this->request->is('post') || $this->request->is('put')) {
            $qcDocument = $this->QcDocument->find('first',array('conditions'=>array('QcDocument.id'=>$id),'recursive'=>-1));
            if($qcDocument){
                $m9orig = $qcDocument['QcDocument']; // M9 correction 28: the stored row, to put back on a failure
                $m9why = $this->_m9_revision_unchanged(isset($this->request->data['QcDocument']['revision_number']) ? $this->request->data['QcDocument']['revision_number'] : null, $id, true); // M9 correction 22d
                if ($m9why !== '') { $this->_m9_ac_log('revision_change_refused', 'rename'); $this->Session->setFlash($m9why); $this->redirect(array('action' => 'rename', $id)); }
                $this->request->data['QcDocument']['revision_number'] = (string)$qcDocument['QcDocument']['revision_number'];
                if($qcDocument['QcDocument']['document_number'] != $this->request->data['QcDocument']['document_number']){
                    // check if document number exists if changed.
                    $docNumCheck = $this->QcDocument->find('count',array(
                        'conditions'=>array(
                            'QcDocument.document_number'=>$this->request->data['QcDocument']['document_number'],
                            'QcDocument.id != '=>$id,
                            'QcDocument.standard_id'=>$qcDocument['QcDocument']['standard_id'],
                        )));
                    if($docNumCheck > 0){
                        $this->Session->setFlash(__('Document Number already exists.'));
                        $this->redirect(array('action' => 'rename',$id));
                    }
                }                
                //find if the document exists;
                // back up existing document
                // if yes, rename the document
                // check if history exists
                // if yes rename the history foler                
                $id = $qcDocument['QcDocument']['id'];
                $file_type = $qcDocument['QcDocument']['file_type'];
                $old_file_name = $qcDocument['QcDocument']['title'];
                $document_number = $qcDocument['QcDocument']['document_number'];
                $document_version = $qcDocument['QcDocument']['revision_number'];
                $old_file_name = $document_number . '-' . $old_file_name . '-' . $document_version;
                $old_file_name = $this->_clean_table_names($old_file_name);
                $old_file_name = $old_file_name. '.' . $file_type;                
                $file = Configure::read('path') . DS . $id . DS . $old_file_name;                                
                // new name
                $qcDocument['QcDocument']['title'] = $this->request->data['QcDocument']['name'];
                $file_type = $qcDocument['QcDocument']['file_type'];
                $file_name = $qcDocument['QcDocument']['title'];
                $document_number = $this->request->data['QcDocument']['document_number'];
                $document_version = $this->request->data['QcDocument']['revision_number'];
                $file_name = $document_number . '-' . $file_name . '-' . $document_version;
                $file_name = $this->_clean_table_names($file_name);
                $file_name = $file_name. '.' . $file_type;                
                $newfile = Configure::read('path') . DS . $id . DS . $file_name;
                if(file_exists($file)){
                    // M9 correction 28 (owner, 6 Nov 2026): every step is checked, in this order: inputs, name collisions, backup,
                    // file, history folder, record. A failure puts back what was already done (MyISAM, no transaction), and
                    // "Document renamed." is shown only when the file, its history folder and the record were all changed.
                    $m9fail = function ($event, $msg) use ($id) { $this->_m9_ac_log('rename_refused', $event); $this->Session->setFlash($msg); $this->redirect(array('action' => 'rename', $id)); };
                    $m9in = function ($k) { return (isset($this->request->data['QcDocument'][$k]) && is_scalar($this->request->data['QcDocument'][$k])) ? trim((string)$this->request->data['QcDocument'][$k]) : ''; };
                    if ($m9in('name') === '' || $m9in('document_number') === '' || $this->_clean_table_names($this->request->data['QcDocument']['name']) === '') $m9fail('input_blank', __('The document name and the document number are required. Nothing was changed.'));
                    $oldfolder = $file.'-hist';
                    $newfolder = $newfile.'-hist';
                    $m9hist = file_exists($oldfolder) && $newfolder !== $oldfolder;
                    if ($newfile !== $file && file_exists($newfile)) $m9fail('target_file_exists', __('A file with the new name already exists for this document. Nothing was changed.'));
                    if ($m9hist && file_exists($newfolder)) $m9fail('target_history_exists', __('A history folder with the new name already exists for this document. Nothing was changed.'));
                    // back up the existing folder (as before, now checked)
                    $m9bk = Configure::read('path'). DS . 'backups';
                    foreach (array($m9bk, $m9bk . DS . $id) as $m9d) { if (false === is_dir($m9d)) { $m9f = new Folder(); if (false === $m9f->create($m9d)) $m9fail('backup_folder', __('Unable to back up the document. Nothing was changed.')); } }
                    $folderToCopy = new Folder(Configure::read('path') . DS . $id);
                    if (false === $folderToCopy->copy(array('to' => $m9bk . DS . $id, 'from' => Configure::read('path') . DS . $id, 'recursive' => true))) $m9fail('backup_copy', __('Unable to back up the document. Nothing was changed.'));
                    $m9fileMoved = false; $m9histMoved = false;
                    $m9undo = function () use ($file, $newfile, $oldfolder, $newfolder, &$m9fileMoved, &$m9histMoved) {
                        $ok = true;
                        if ($m9histMoved) $ok = @rename($newfolder, $oldfolder) && $ok;
                        if ($m9fileMoved) $ok = @rename($newfile, $file) && $ok;
                        return $ok;
                    };
                    if ($newfile !== $file) {
                        if (false === @rename($file, $newfile)) $m9fail('file_rename_failed', __('The file could not be renamed. Nothing was changed.'));
                        $m9fileMoved = true;
                    }
                    if ($m9hist) {
                        if (false === @rename($oldfolder, $newfolder)) {
                            $m9back = $m9undo();
                            $this->_m9_ac_log('rename_failed_restored', 'history_rename' . ($m9back ? '' : ' undo_failed'));
                            $this->Session->setFlash($m9back ? __('The history folder could not be renamed. Nothing was changed.') : __('The history folder could not be renamed and the file could not be put back. Please contact the administrator.'));
                            $this->redirect(array('action' => 'rename', $id));
                        }
                        $m9histMoved = true;
                    }
                    // update record
                    $qcDocument['QcDocument']['name'] = $this->request->data['QcDocument']['name'];
                    $qcDocument['QcDocument']['title'] = $this->_clean_table_names($this->request->data['QcDocument']['name']);
                    $qcDocument['QcDocument']['document_number'] = $this->request->data['QcDocument']['document_number'];
                    $qcDocument['QcDocument']['revision_number'] = $this->request->data['QcDocument']['revision_number'];
                    $this->QcDocument->create();
                    try { $m9ok = (bool)$this->QcDocument->save($qcDocument,false); } catch (Exception $m9e) { $m9ok = false; } // a database error throws
                    if (false === $m9ok) {
                        $m9back = $m9undo();
                        $m9rec = $this->_m9_put_back('QcDocument', $m9orig);
                        $this->_m9_ac_log('rename_failed_restored', 'record_save' . ($m9back ? '' : ' undo_failed') . ($m9rec ? '' : ' record_restore_failed:' . $this->m9PutBackDiff));
                        $this->Session->setFlash(($m9back && $m9rec) ? __('The document record could not be saved. Nothing was changed.') : __('The document record could not be saved and the earlier steps could not all be put back. Please contact the administrator.'));
                        $this->redirect(array('action' => 'rename', $id));
                    }
                    $this->_m9_ac_log('rename_saved', substr((string)$id, 0, 8));
                    $this->Session->setFlash(__('Document renamed.'));
                    $this->redirect(array('action' => 'view',$id));
                }else{
                    $this->Session->setFlash(__('Document does not exists.'));
                    $this->redirect(array('action' => 'view',$id));  
                }
            }else{
                $this->Session->setFlash(__('Document does not exists.'));
                $this->redirect(array('action' => 'view',$id));
            }
        }else{
            $access = $this->request->data['Access'];
            $options = array('conditions' => array('QcDocument.' . $this->QcDocument->primaryKey => $id));
            $this->request->data = $this->QcDocument->find('first', $options);
            if($this->Session->read('User.id') != $this->request->data['QcDocument']['created_by'])$this->_doc_access($this->request->data,$access);

            if($this->request->data['QcDocument']['document_status'] == 3){
                $this->Session->setFlash(__('Can not edit this document. This document is under revision.'));
                $this->redirect(array('action' => 'view', $id,'timestamp'=>date('ymdhis')));
            }            
            if($this->request->data['QcDocument']['document_status'] == 6){
                $this->Session->setFlash(__('You must update this document.'));
                $this->redirect(array('action' => 'update_revision', $id,'timestamp'=>date('ymdhis')));
            }
            if($this->request->data['QcDocument']['archived'] == 1){
                $this->Session->setFlash(__('You can not edit this document, its archived.'));
                $this->redirect(array('action' => 'view', $id,'timestamp'=>date('ymdhis')));
            }
        }
    }

    public function document_list($user_id = null){

        if($user_id){
            $this->loadModel('User');
            $user = $this->User->find('first',array('recursive'=>-1,'conditions'=>array('User.id'=>$user_id)));
            $this->set('user',$user);
        }

        $this->paginate = array(
            'recursive'=>0,
            'limit'=>25,
            'conditions'=>array('QcDocument.parent_document_id'=>array(NULL,-1,'')),
            'fields'=>array(
                'QcDocument.id','QcDocument.title','QcDocument.name', 'QcDocument.document_number', 'QcDocument.standard_id','QcDocument.user_id','QcDocument.branches','QcDocument.departments','QcDocument.designations','QcDocument.editors','QcDocument.prepared_by','QcDocument.approved_by',
                'PreparedBy.id',
                'PreparedBy.name',
                'ApprovedBy.id',
                'ApprovedBy.name',
            )
        );
        $qcDocuments = $this->paginate();
        $this->set('qcDocuments',$qcDocuments);
    }
}
