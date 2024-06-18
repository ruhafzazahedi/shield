<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<head>
    <meta name="x-apple-disable-message-reformatting">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no, date=no, address=no, phone=no">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title><?= lang('Auth.phoneActivateSubject') ?></title>
</head>

<body>
    <p><?= lang('Auth.phoneActivatePhoneBody') ?></p>
    <div style="text-align: center">
        <h1><?= $code ?></h1>
    </div>
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="width: 100%;" width="100%">
        <tbody>
            <tr>
                <td style="line-height: 20px; font-size: 20px; width: 100%; height: 20px; margin: 0;" align="left" width="100%" height="20">
                    &#160;
                </td>
            </tr>
        </tbody>
    </table>
    <b><?= lang('Auth.phoneInfo') ?></b>
    <p><?= lang('Auth.phoneIpAddress') ?> <?= esc($ipAddress) ?></p>
    <p><?= lang('Auth.phoneDevice') ?> <?= esc($userAgent) ?></p>
    <p><?= lang('Auth.phoneDate') ?> <?= esc($date) ?></p>
</body>

</html>
