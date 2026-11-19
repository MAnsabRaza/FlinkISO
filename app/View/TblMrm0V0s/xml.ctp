<?php if($tblMrm0V0s){
    	foreach ($tblMrm0V0s as $tblMrm0V0):    
$def = $tblMrm0V0['TblMrm0V0']['meeting_number'];
			$result["TblMrm0V0"]["MeetingNumber"] = $tblMrm0V0['TblMrm0V0']['meeting_number'];
			$result["TblMrm0V0"]["ProposedBy"] = $tblMrm0V0['ProposedBy']['name'];
			$result["TblMrm0V0"]["MeetingStatus"] = $customArray['meetingStatuses'][$tblMrm0V0['TblMrm0V0']['meeting_status']];
        foreach($linkedTables as $linkedTable){
                $res = $this->requestAction(array('controller'=>$linkedTable['CustomTable']['table_name'], 'action'=>'xml',$tblMrm0V0['TblMrm0V0']['id'],$linkedTable['CustomTable']['id'])); 
                $result[Inflector::classify($linkedTable['CustomTable']['table_name'])] = $res;
        }

			$result["TblMrm0V0"]["PreparedBy"] = $tblMrm0V0["PreparedBy"]["name"];
			$result["TblMrm0V0"]["ApprovedBy"] = $tblMrm0V0["ApprovedBy"]["name"];if($tblMrm0V0['TblMrm0V0']['attainted_by'])$dt =   date(Configure::read('dateTimeFormat'),strtotime($tblMrm0V0['TblMrm0V0']['created'])) ;
    else $dt =  "--";
			$result["TblMrm0V0"]["Created"] = $dt;
$results[str_replace(' ','',$def)] = $result;
	endforeach; 
} ?>
<?php
    $returnjson['results']['result'] = $results;
    $returnjson['results']['paging'] = $this->Paginator->request->params['paging'];
    $xml = Xml::fromArray($returnjson, array('pretty' => true));
    $this->response->body($xml->asXML());
    $this->response->statusCode(200);
    $this->response->type('xml');
    $this->response->send();
    exit;   
?>