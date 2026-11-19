<?php
App::uses('AppController', 'Controller');
/**
 * ApprovalComments Controller
 *
 * @property ApprovalComment $ApprovalComment
 * @property PaginatorComponent $Paginator
 */
class ApprovalCommentsController extends AppController {
    /**
     * Components
     *
     * @var array
     */
    public $components = array('Paginator');
    
    
    public function approval_comments($approval_id = null,$custom_table_id = null,$approval_step_id = null,$prepared_by = null) {
    
        $this->layout = 'ajax';

        if(!$this->request->params['named']['approval_step_id']){
            echo "Approval Step Missing.";
            exit;
        }

        $approvalComments = $this->ApprovalComment->find('all', array('order' => array('ApprovalComment.sr_no' => 'ASC'), 'conditions' => array('ApprovalComment.approval_id' => $this->request->params['named']['approval_id'])));
        $this->set('approvalComments', $approvalComments);


        // get record details
        $approval = $this->ApprovalComment->Approval->find('first',array('recursive'=>0, 'conditions'=>array('Approval.id'=>$this->request->params['named']['approval_id'])));
        if (!$approvalComments) {            
            // if no comments get approval details
            $approval = $this->ApprovalComment->Approval->find('first', array('conditions' => array('Approval.id' => $this->request->params['named']['approval_id'])));
            $this->set('approval', $approval);
        }
        // M9 fix (staging, 14 Oct 2026): users eligible for the next step, built with the function the edit page uses
        // (_get_approver_list() has no body, so the list was always empty). The sender is added below for replies.
        $approversLists = array();
        $this->loadModel('ApprovalStep');
        $stepNow = $this->ApprovalStep->find('first', array('recursive' => -1, 'conditions' => array('ApprovalStep.id' => $this->request->params['named']['approval_step_id'])));
        if ($stepNow) {
            $stepNext = $this->ApprovalStep->find('first', array('recursive' => -1, 'conditions' => array(
                'ApprovalStep.approval_process_id' => $stepNow['ApprovalStep']['approval_process_id'],
                'ApprovalStep.process_step' => $stepNow['ApprovalStep']['process_step'] + 1)));
            if ($stepNext) $approversLists = $this->_get_approver_lists($this->Session->read('User.id'), $stepNext['ApprovalStep']);
        }
        $approversLists[$approval['Approval']['from']] = $approval['From']['name'];
        $this->set('approversLists', $approversLists);

        $this->_fetch_approval_steps();

        $this->loadModel('ApprovalStep');
        $currentStep = $this->ApprovalStep->find('first',array('conditions'=>array('ApprovalStep.id'=>$this->request->params['named']['approval_step_id'])));
        $this->set('currentStep',$currentStep);
        $this->set('prepared_by',$this->request->params['named']['prepared_by']);
        $this->set('approval_step_id', $approval_step_id ? $approval_step_id : (isset($this->request->params['named']['approval_step_id']) ? $this->request->params['named']['approval_step_id'] : null)); // M9 fix (staging, 26 Oct 2026): the panel sends the step as a named parameter
    }



    public function add_response($id = null, $response = null, $to = null, $approval_step_id = null) {
        
        $id = $this->request->data['id'];
        $response = $this->request->data['response'];
        $to = $this->request->data['to'];
        $approval_id = $this->request->data['approval_id'];
        $approval_status = $this->request->data['approval_status'];
        $approval_step_id = $this->request->data['approval_step_id'];
        
        if($to == -1 || $id == -1 || $approval_id == -1 || $approval_step_id == ''){
            $this->set('responseresult','Approval process step is missing.');            
        }
        
        if(!$approval_step_id){
            $this->set('responseresult','Failed to procees. Approval process step is missing.');            
        }

        if($approval_status == 1){
            $this->loadModel('ApprovalStep');
            $currentStep = $this->ApprovalStep->find('first',array('recursive'=>-1, 'conditions'=>array('ApprovalStep.id'=>$approval_step_id)));
            if($currentStep){
                $next = $currentStep['ApprovalStep']['process_step'] + 1;
                $nextStep = $this->ApprovalStep->find('first',array('recursive'=>-1, 'conditions'=>array(
                    'ApprovalStep.approval_process_id'=>$currentStep['ApprovalStep']['approval_process_id'],
                    'ApprovalStep.process_step'=>$next,
                )));
                
                if($nextStep){
                    $approval = $this->ApprovalComment->Approval->find('first',array('recursive'=>-1,'conditions'=>array('Approval.id'=>$approval_id)));
                    if($approval){
                        $approval['Approval']['status'] = $approval['Approval']['approval_status'] = 1;
                        $approval['Approval']['approver_comments'] = $response;
                        $this->ApprovalComment->Approval->create();
                        $this->ApprovalComment->Approval->save($approval,false);
                    }

                    $approvalComment = $this->ApprovalComment->find('first',array('recursive'=>-1,'conditions'=>array('ApprovalComment.id'=>$id)));
                    if($approvalComment){
                        $approvalComment['ApprovalComment']['response_status'] = 2;
                        $approvalComment['ApprovalComment']['response'] = $response;
                        $this->ApprovalComment->create();
                        $this->ApprovalComment->save($approvalComment,false);
                    }

                    $model = $approval['Approval']['model_name'];
                    $this->loadModel($model);
                    $rec = $this->$model->find('first', array('conditions' => array($model . '.id' => $approval['Approval']['record']), 'recursive' => - 1));
                    if ($rec) {
                        if($currentStep['ApprovalStep']['send_to_publishers'] == true){
                            $rec[$model]['publish'] = 1;
                            $rec[$model]['published_date'] = date('Y-m-d');
                            $rec[$model]['published_by'] = $this->Session->read('User.employee_id');
                        }
                        if($currentStep['ApprovalStep']['send_to_approvers'] == true){
                            $rec[$model]['approval_date'] = date('Y-m-d');
                            $rec[$model]['approved_by'] = $this->Session->read('User.employee_id');
                        }
                        if($currentStep['ApprovalStep']['send_to_reviwers'] == true){
                            $rec[$model]['date_of_review'] = date('Y-m-d');
                            $rec[$model]['reviewed_by'] = $this->Session->read('User.employee_id');
                        }
                        $rec[$model]['approval_step_id'] = $nextStep['ApprovalStep']['id'];
                        $this->$model->create();
                        if($this->$model->save($rec,false)){
                            $this->set('responseresult','Comment added.');  
                            $this->_sent_approval_email($to,1,$response,$model);
                            // M9 fix (staging, 14 Oct 2026): create the pending approval for the person chosen for the next step,
                            // so the record reaches them without being sent again from the edit page. Only a user eligible for
                            // the next step is accepted.
                            $eligible = $this->_get_approver_lists($this->Session->read('User.id'), $nextStep['ApprovalStep']);
                            if ($to && $to != -1 && isset($eligible[$to])) {
                                $nextApproval = array('Approval' => array(
                                    'title' => $approval['Approval']['title'],
                                    'controller_name' => $approval['Approval']['controller_name'],
                                    'model_name' => $approval['Approval']['model_name'],
                                    'record' => $approval['Approval']['record'],
                                    'from' => $this->Session->read('User.id'),
                                    'user_id' => $to,
                                    'approval_status' => 0,
                                    'approval_mode' => $approval['Approval']['approval_mode'],
                                    'approval_type' => $approval['Approval']['approval_type'],
                                    'comments' => $response,
                                    'approval_step_id' => $nextStep['ApprovalStep']['id'],
                                    'approval_cycle' => $approval['Approval']['approval_cycle'],
                                ));
                                $this->ApprovalComment->Approval->create();
                                $this->ApprovalComment->Approval->save($nextApproval, false);
                            } else {
                                $this->set('responseresult', 'Comment added. No eligible user was selected for the next step, so no approval was sent.');
                            }
                        }else{
                            $this->set('responseresult','Failed to add response.');                            
                        }   
                    }
                                        
                }else{
                    $approval = $this->ApprovalComment->Approval->find('first',array('recursive'=>-1,'conditions'=>array('Approval.id'=>$approval_id)));
                    if($approval){
                        $approval['Approval']['status'] = $approval['Approval']['approval_status'] = 1;
                        $approval['Approval']['approver_comments'] = $response;
                        $this->ApprovalComment->Approval->create();
                        $this->ApprovalComment->Approval->save($approval,false);
                    }

                    $approvalComment = $this->ApprovalComment->find('first',array('recursive'=>-1,'conditions'=>array('ApprovalComment.id'=>$id)));
                    if($approvalComment){
                        $approvalComment['ApprovalComment']['response_status'] = 2;
                        $approvalComment['ApprovalComment']['response'] = $response;
                        $this->ApprovalComment->create();
                        if($this->ApprovalComment->save($approvalComment,false)){
                            $this->set('responseresult','Comment added.');  
                        }else{
                            $this->set('responseresult','Failed to add response.');                            
                        }
                    }
                    
                    $model = $approval['Approval']['model_name'];
                    $this->loadModel($model);
                    $rec = $this->$model->find('first', array('conditions' => array($model . '.id' => $approval['Approval']['record']), 'recursive' => - 1));

                    if ($rec) {
                        if($currentStep['ApprovalStep']['send_to_publishers'] == true){
                            $rec[$model]['published_date'] = date('Y-m-d');
                            $rec[$model]['published_by'] = $this->Session->read('User.employee_id');
                        }else{
                            if(!$rec[$model]['published_by']){
                                $rec[$model]['published_date'] = date('Y-m-d');
                                $rec[$model]['published_by'] = $this->Session->read('User.employee_id');
                            }
                        }
                        if($currentStep['ApprovalStep']['send_to_approvers'] == true){
                            $rec[$model]['approval_date'] = date('Y-m-d');
                            $rec[$model]['approved_by'] = $this->Session->read('User.employee_id');
                        }else{
                            if(!$rec[$model]['approved_by']){
                                $rec[$model]['approval_date'] = date('Y-m-d');
                                $rec[$model]['approved_by'] = $this->Session->read('User.employee_id');
                            }
                        }
                        if($currentStep['ApprovalStep']['send_to_reviwers'] == true){
                            $rec[$model]['date_of_review'] = date('Y-m-d');
                            $rec[$model]['reviewed_by'] = $this->Session->read('User.employee_id');
                        }else{
                            if(!$rec[$model]['reviewed_by']){
                                $rec[$model]['date_of_review'] = date('Y-m-d');
                                $rec[$model]['reviewed_by'] = $this->Session->read('User.employee_id');
                            }
                        }
                        $rec[$model]['record_status'] = 1;
                        $rec[$model]['publish'] = 1;
                        $this->$model->create();
                        if($this->$model->save($rec,false)){
                            $this->set('responseresult','Comment added.'); 
                            $this->_sent_approval_email($to,1,$response,$model);
                        }else{
                            $this->set('responseresult','Failed to add response.');                            
                        }
                    }
                }                
            }                    
            if(!$nextStep){
                
            }            

        }else{
            $approvalComment = $this->ApprovalComment->find('first', array('conditions' => array('ApprovalComment.id' => $id)));
            if ($approvalComment) {
                //update response_status
                $approvalComment['ApprovalComment']['response_status'] = 1;
                $approvalComment['ApprovalComment']['response'] = $response;
                $this->ApprovalComment->create();
                if ($this->ApprovalComment->save($approvalComment, false)) {
                    // create new record and send to to user from this user
                    unset($this->ApprovalComment->id);
                    unset($approvalComment['ApprovalComment']['id']);
                    unset($approvalComment['ApprovalComment']['sr_no']);
                    unset($approvalComment['ApprovalComment']['created']);
                    unset($approvalComment['ApprovalComment']['created_by']);
                    unset($approvalComment['ApprovalComment']['comments']);
                    unset($approvalComment['ApprovalComment']['response']);
                    unset($approvalComment['ApprovalComment']['from']);
                    unset($approvalComment['ApprovalComment']['user_id']);
                    unset($approvalComment['ApprovalComment']['modified']);
                    unset($approvalComment['ApprovalComment']['modified_by']);
                    $approvalComment['ApprovalComment']['from'] = $this->Session->read('User.id');
                    $approvalComment['ApprovalComment']['user_id'] = $to;
                    $approvalComment['ApprovalComment']['comments'] = $response;
                    $approvalComment['ApprovalComment']['response_status'] = 0;                    
                    $this->ApprovalComment->create();
                    if ($this->ApprovalComment->save($approvalComment['ApprovalComment'],false)) {
                        $this->_sent_approval_email($to,0,$response,$model);
                    } else {
                        $this->set('responseresult','Failed to add response.');
                    }
                }
            } else {
                // add first
                if ($approval_id) {
                    $approval = $this->ApprovalComment->Approval->find('first', array('conditions' => array('Approval.id' => $approval_id), 'recursive' => - 1));
                    if ($approval) {
                        $approvalComment['ApprovalComment']['from'] = $this->Session->read('User.id');
                        $approvalComment['ApprovalComment']['user_id'] = $to;
                        $approvalComment['ApprovalComment']['approval_id'] = $approval_id;
                        $approvalComment['ApprovalComment']['response_status'] = 0;
                        $approvalComment['ApprovalComment']['comments'] = $response;
                        $this->ApprovalComment->create();
                        if ($this->ApprovalComment->save($approvalComment['ApprovalComment'],false)) {
                            $this->_sent_approval_email($to,0,$response,$model);
                        } else {
                            $this->set('responseresult','Failed to add response.');
                        }
                    }
                }
            }
        }
    }
    
    public function _sent_approval_email($to = null,$message = null,$response = null,$model = null){
        $this->loadModel('User');
        $user = $this->User->find('first',array('conditions'=>array('User.id'=>$to)));
        if($user){
            if ($user['Employee']['office_email'] != '') {
                $email = $user['Employee']['office_email'];
            } else if ($user['Employee']['personal_email'] != '') {
                $email = $user['Employee']['personal_email'];
            }    
        }        
        if ($email) {
            if($message == 1)$subject = 'FlinkISO: Record Approved.';
            else $subject = 'FlinkISO: Approvals';

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
}