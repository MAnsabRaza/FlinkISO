
<?php if($tblAuditSchedule0V0s){
    	foreach ($tblAuditSchedule0V0s as $tblAuditSchedule0V0):    

			$result["TblAuditSchedule0V0"]["Audit Number"] = $tblAuditSchedule0V0['TblAuditSchedule0V0']['audit_number'];
			$result["TblAuditSchedule0V0"]["Current Status"] = $customArray['currentStatuses'][$tblAuditSchedule0V0['TblAuditSchedule0V0']['current_status']];
        foreach($linkedTables as $linkedTable){
                $res = $this->requestAction(array('controller'=>$linkedTable['CustomTable']['table_name'], 'action'=>'json',$tblAuditSchedule0V0['TblAuditSchedule0V0']['id'],$linkedTable['CustomTable']['id'])); 
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