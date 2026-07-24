<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\SharedInbox::with('account')->get() as $i) {
    echo '#'.$i->id.' type='.$i->type.' name='.$i->name
        .' email='.$i->email
        .' ext='.$i->external_mailbox
        .' acct_id='.$i->outlook_mail_account_id
        .' acct='.($i->account->email ?? 'none')
        ."\n";
}

echo "\nAccounts:\n";
foreach (App\Models\OutlookMailAccount::all() as $a) {
    echo '#'.$a->id.' user='.$a->user_id.' email='.$a->email."\n";
}

echo "\nRecent conversations by inbox:\n";
foreach (App\Models\InboxConversation::orderByDesc('id')->limit(20)->get(['id','shared_inbox_id','folder','from_email','subject']) as $c) {
    echo '#'.$c->id.' inbox='.$c->shared_inbox_id.' folder='.$c->folder.' from='.$c->from_email.' subj='.mb_substr((string)$c->subject,0,40)."\n";
}
