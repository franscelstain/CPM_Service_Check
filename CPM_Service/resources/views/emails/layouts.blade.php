<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta  name="viewport" content="width=display-width, initial-scale=1.0, maximum-scale=1.0," />
		<link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i" rel="stylesheet" />
		<link href='https://fonts.googleapis.com/css?family=Open+Sans:400,300,300italic,400italic,600,700,600italic,700italic,800,800italic' rel='stylesheet' type='text/css' />		
		<style type="text/css">		
			html { width: 100%; }
			body {margin:0; padding:0; width:100%; -webkit-text-size-adjust:none; -ms-text-size-adjust:none;}
			img { display: block !important; border:0; -ms-interpolation-mode:bicubic;}
			.ReadMsgBody { width: 100%;}
			.ExternalClass {width: 100%;}
			.ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div { line-height: 100%; }
			.images {display:block !important; width:100% !important;}				
			.Heading {font-family:'Roboto', Arial, Helvetica Neue, Helvetica, sans-serif !important;}
			.MsoNormal {font-family:'Open Sans', Arial, Helvetica Neue, Helvetica, sans-serif !important;}
			p {margin:0 !important; padding:0 !important;}
			.display-button td, .display-button a  {font-family:'Open Sans', Arial, Helvetica Neue, Helvetica, sans-serif !important;}
			.display-button a:hover {text-decoration:none !important;}
			.width-auto {
				width: auto !important;
			}
			.width600 {
				width:600px;
			}
			.width800 {
				width:800px !important;
				max-width:800px !important;
			}
			.saf-table {
				display:table !important;
			}
			/* MEDIA QUIRES */
			@media only screen and (max-width:799px)
            {
                body {width:auto !important;}
				.display-width {width:100% !important;}	
				.res-padding {padding:0 20px !important;}	
				.display-width-inner {width:600px !important;}
				.res-center {text-align:center !important; width:100% !important; }
				.width800 {
					width:100% !important;
					max-width:100% !important;
				}
				
            }
			@media only screen and (max-width:639px)
			{
				.display-width-inner, .display-width-child {width:100% !important;}
				td[class="height-hidden"] {display:none !important;}
				.height10 {height:10px !important;}
				.txt-center {text-align:center !important;}
				.image-center{margin:0 auto !important; display:table !important;}
				.butn-center{margin:0 auto; display:table;}
				.width272 {
				    width:272px !important;  
				}
				.div-width {				
					display: block !important;
					width: 100% !important;
					max-width: 100% !important;
				}
				.saf-table {
					display:block !important;
				}				
			}
			@media only screen and (max-width:480px) 
			{
				.button-width .display-button {width:auto !important;}
				.div-width {display: block !important;
					width: 100% !important;
					max-width: 100% !important;					
				}	
			}
			@media only screen and (max-width:331px)
			{
				.display-width-child .width272 { width:100% !important;}
				.header-width{max-width:260px !important;}
			}
			span.preheader { display: none !important; }
		</style>
	</head>
	<body>
		<!--[if mso]>
			<style >
				.MsoNormal{font-family: Arial, Helvetica Neue, Helvetica, sans-serif !important;}
				.Heading {font-family: Arial, Helvetica Neue, Helvetica, sans-serif !important;}
				.display-button td, .display-button a, a {font-family: Arial, Helvetica Neue, Helvetica, sans-serif !important;}
				body table, body table td, table[width=800] { padding:0 !important; margin:0 !important; border:0 !important; border-collapse:collapse !important; mso-table-lspace:0pt !important; mso-table-rspace:0pt !important; outline:0 !important; }
			</style>
		<![endif]-->
        @php
            $change_mail = !empty($mail->data->email_change) ? !is_array($mail->data->email_change) ? json_decode($mail->data->email_change, true) : $mail->data->email_change : [];
            $message     = !empty($change_mail) && !empty($mail->mail['new']) && is_array($mail->mail['new']) ? str_replace($change_mail, $mail->mail['new'], $mail->data->email_content_text) : $mail->data->email_content_text;        
            $old         = !is_array($mail->data->layout_change) ? json_decode($mail->data->layout_change, true) : $mail->data->layout_change;
            $new         = !empty($mail->mail['change_layout']) && is_array($mail->mail['change_layout']) ? array_merge([$message], $mail->mail['change_layout']) : [$message];
        @endphp
        
        @switch ($mail->data->layout_title)
            @case('Default')
                @php $new = array_merge($new, [date('d F Y')]); @endphp
                @break
        @endswitch
        
        {!! html_entity_decode(str_replace($old, $new, $mail->data->layout_content)) !!}
	</body>
</html>