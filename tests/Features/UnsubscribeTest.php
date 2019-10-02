<?php

namespace Spatie\EmailCampaigns\Tests\Features;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\EmailCampaigns\Jobs\SendCampaignJob;
use Spatie\EmailCampaigns\Tests\Factories\EmailCampaignFactory;
use Spatie\EmailCampaigns\Tests\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class UnsubscribeTest extends TestCase
{
    /** @var \Spatie\EmailCampaigns\Models\EmailCampaign */
    private $campaign;

    /** @var string */
    private $mailedUnsubscribeLink;

    /** @var \Spatie\EmailCampaigns\Models\EmailList */
    private $emailList;

    /** @var \Spatie\EmailCampaigns\Models\EmailListSubscriber */
    private $subscriber;

    public function setUp(): void
    {
        parent::setUp();

        $this->campaign = (new EmailCampaignFactory())->withSubscriberCount(1)->create([
            'html' => '<a href="@@unsubscribeLink@@">Unsubscribe</a>',
        ]);

        $this->emailList = $this->campaign->emailList;

        $this->subscriber = $this->campaign->emailList->subscribers->first();

    }

    /** @test */
    public function it_can_unsubscribe_from_a_list()
    {
        $this->withoutExceptionHandling();

        $this->sendCampaign();

        $this->assertTrue($this->subscriber->isSubscribedTo($this->emailList));

        $content = $this
            ->get($this->mailedUnsubscribeLink)
            ->assertSuccessful()
            ->baseResponse->content();

        $this->assertStringContainsString('unsubscribed', $content);

        $this->assertFalse($this->subscriber->isSubscribedTo($this->emailList));
    }

    protected function sendCampaign()
    {
        Event::listen(MessageSent::class, function (MessageSent $event) {
            $link = (new Crawler($event->message->getBody()))
                ->filter('a')->first()->attr('href');

            $this->assertStringStartsWith('http://localhost', $link);

            $this->mailedUnsubscribeLink = Str::after($link, 'http://localhost');
        });

        dispatch(new SendCampaignJob($this->campaign));
    }
}

