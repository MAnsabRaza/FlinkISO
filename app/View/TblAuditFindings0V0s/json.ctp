
<?php if($tblAuditFindings0V0s){
    	foreach ($tblAuditFindings0V0s as $tblAuditFindings0V0):    

			$result["TblAuditFindings0V0"]["Finding Number"] = $tblAuditFindings0V0['TblAuditFindings0V0']['finding_number'];
			$result["TblAuditFindings0V0"]["Auditor"] = $tblAuditFindings0V0['Auditor']['name'];

			$result["TblAuditFindings0V0"]["Auditee"] = $tblAuditFindings0V0['Auditee']['name'];

        foreach($linkedTables as $linkedTable){
                $res = $this->requestAction(array('controller'=>$linkedTable['CustomTable']['table_name'], 'action'=>'json',$tblAuditFindings0V0['TblAuditFindings0V0']['id'],$linkedTable['CustomTable']['id'])); 
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