
<?php if($tblMrm0V0s){
    	foreach ($tblMrm0V0s as $tblMrm0V0):    

			$result["TblMrm0V0"]["Meeting Number"] = $tblMrm0V0['TblMrm0V0']['meeting_number'];
			$result["TblMrm0V0"]["Proposed By"] = $tblMrm0V0['ProposedBy']['name'];

			$result["TblMrm0V0"]["Meeting Status"] = $customArray['meetingStatuses'][$tblMrm0V0['TblMrm0V0']['meeting_status']];
        foreach($linkedTables as $linkedTable){
                $res = $this->requestAction(array('controller'=>$linkedTable['CustomTable']['table_name'], 'action'=>'json',$tblMrm0V0['TblMrm0V0']['id'],$linkedTable['CustomTable']['id'])); 
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