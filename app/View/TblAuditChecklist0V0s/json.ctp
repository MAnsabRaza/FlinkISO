
<?php if($tblAuditChecklist0V0s){
    	foreach ($tblAuditChecklist0V0s as $tblAuditChecklist0V0):    

			$result["TblAuditChecklist0V0"]["Checklist Title"] = $tblAuditChecklist0V0['TblAuditChecklist0V0']['checklist_title'];
			
            if($tblAuditChecklist0V0['TblAuditChecklist0V0']['date_added'])$dt =   date(Configure::read('dateFormat'),strtotime($tblAuditChecklist0V0['TblAuditChecklist0V0']['date_added'])) ;
            else $dt =  "--";$result["TblAuditChecklist0V0"]["Date Added"] = $dt;
			$result["TblAuditChecklist0V0"]["Added By"] = $tblAuditChecklist0V0['AddedBy']['name'];

			$result["TblAuditChecklist0V0"]["Comments"] = $tblAuditChecklist0V0['TblAuditChecklist0V0']['comments'];
        foreach($linkedTables as $linkedTable){
                $res = $this->requestAction(array('controller'=>$linkedTable['CustomTable']['table_name'], 'action'=>'json',$tblAuditChecklist0V0['TblAuditChecklist0V0']['id'],$linkedTable['CustomTable']['id'])); 
                $result[Inflector::classify($linkedTable['CustomTable']['table_name'])] = $res;
        }

$results[] = $result;
	endforeach; 
} ?>
<?php
    $returnjson['results'] = $results;
    $returnjson['paging'] = $this->Paginator->request->params['paging'];
    echo json_encode($returnjson); 
    $this->response->statusCode(200);
    $this->response->type('json');
    $this->response->send();
    exit;   
?>