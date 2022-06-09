$helpdesk_url = 'http://helpdesk.example.org'
$action = 'new'
$type = 'test'
$to = 'sas'
$vhost = '7701-W0000'
$id = ''
$message = [System.Web.HttpUtility]::UrlEncode("THIS IS A TEST. <a href=`"http://ya.ru/`" target=`"_blank`">Link</a>")

$result = (New-Object System.Net.WebClient).DownloadString('{0}/ExtAlert.aspx/?Source=cdb&Action={1}&Type={2}&To={3}&Host={4}&Id={5}&Message={6}' -f @($helpdesk_url, $action, $type, $to, $vhost, $id, $message))

$result
