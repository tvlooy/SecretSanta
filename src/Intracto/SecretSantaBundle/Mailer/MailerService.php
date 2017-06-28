<?php

namespace Intracto\SecretSantaBundle\Mailer;

use Doctrine\ORM\EntityManager;
use Intracto\SecretSantaBundle\Service\UnsubscribeService;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Intracto\SecretSantaBundle\Entity\Participant;
use Intracto\SecretSantaBundle\Entity\Party;

class MailerService
{
    /** @var \Swift_Mailer */
    public $mailer;
    /** @var EntityManager */
    public $em;
    /** @var EngineInterface */
    public $templating;
    /** @var TranslatorInterface */
    public $translator;
    /** @var \Symfony\Bundle\FrameworkBundle\Routing\Router */
    public $routing;
    /** @var UnsubscribeService */
    public $unsubscribeService;
    public $noreplyEmail;

    /**
     * @param \Swift_Mailer       $mailer             a regular SMTP mailer, bad monitoring, cheap
     * @param EntityManager       $em
     * @param EngineInterface     $templating
     * @param TranslatorInterface $translator
     * @param Router              $routing
     * @param UnsubscribeService  $unsubscribeService
     * @param $noreplyEmail
     */
    public function __construct(
        \Swift_Mailer $mailer,
        EntityManager $em,
        EngineInterface $templating,
        TranslatorInterface $translator,
        Router $routing,
        UnsubscribeService $unsubscribeService,
        $noreplyEmail
    ) {
        $this->mailer = $mailer;
        $this->em = $em;
        $this->templating = $templating;
        $this->translator = $translator;
        $this->routing = $routing;
        $this->unsubscribeService = $unsubscribeService;
        $this->noreplyEmail = $noreplyEmail;
    }

    /**
     * @param Party $party
     */
    public function sendPendingConfirmationMail(Party $party)
    {
        $this->translator->setLocale($party->getLocale());

        $message = (new \Swift_Message())
            ->setSubject($this->translator->trans('emails-pendingConfirmation.subject'))
            ->setFrom($this->noreplyEmail, $this->translator->trans('emails-base_email.sender'))
            ->setTo($party->getOwnerEmail())
            ->setBody(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:pendingConfirmation.txt.twig',
                    ['party' => $party]
                )
            )
            ->addPart(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:pendingConfirmation.html.twig',
                    ['party' => $party]
                ),
                'text/html'
            );
        $this->mailer->send($message);
    }

    /**
     * Sends out all mails for a Party.
     *
     * @param Party $party
     */
    public function sendSecretSantaMailsForParty(Party $party)
    {
        $party->setSentdate(new \DateTime('now'));
        $this->em->flush($party);

        foreach ($party->getParticipants() as $participant) {
            $this->sendSecretSantaMailForParticipant($participant);
        }
    }

    /**
     * Sends out mail for a Participant.
     *
     * @param Participant $participant
     */
    public function sendSecretSantaMailForParticipant(Participant $participant)
    {
        $this->translator->setLocale($participant->getParty()->getLocale());

        // We wrap the admin's message into our own message and from 19/apr/2017 we no longer save
        // our own message in the DB. Don't wrap older parties here to prevent the message from occuring twice.
        if ($participant->getParty()->getCreationDate() < new \DateTime('2017-04-20')) {
            $message = $participant->getParty()->getMessage();
        } else {
            $dateFormatter = \IntlDateFormatter::create(
                $participant->getParty()->getLocale(),
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::NONE
            );

            $message = $this->translator->trans('party_controller.created.message', [
                '%amount%' => $participant->getParty()->getAmount(),
                '%eventdate%' => $dateFormatter->format($participant->getParty()->getEventdate()->getTimestamp()),
                '%location%' => $participant->getParty()->getLocation(),
                '%message%' => $participant->getParty()->getMessage(),
            ]);
        }

        $participant->setInvitationSentDate(new \DateTime('now'));
        $this->em->flush($participant);

        $message = str_replace(
            ['(NAME)', '(ADMINISTRATOR)'],
            [$participant->getName(), $participant->getParty()->getOwnerName()],
            $message
        );

        $mail = (new \Swift_Message())
            ->setSubject($this->translator->trans('emails-participant.subject'))
            ->setFrom($this->noreplyEmail, $participant->getParty()->getOwnerName())
            ->setReplyTo([$participant->getParty()->getOwnerEmail() => $participant->getParty()->getOwnerName()])
            ->setTo($participant->getEmail(), $participant->getName())
            ->setBody(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:participant.html.twig',
                    [
                        'message' => $message,
                        'participant' => $participant,
                    ]
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:participant.txt.twig',
                    [
                        'message' => $message,
                        'participant' => $participant,
                    ]
                ),
                'text/plain'
            );
        $mail->getHeaders()->addTextHeader('List-Unsubscribe', $this->unsubscribeService->getUnsubscribeLink($participant));
        $this->mailer->send($mail);
    }

    /**
     * @param $email
     *
     * @return bool
     */
    public function sendForgotLinkMail($email)
    {
        $participatingIn = $this->em->getRepository('IntractoSecretSantaBundle:Participant')->findAllParticipantsForForgotEmail($email);
        $adminOf = $this->em->getRepository('IntractoSecretSantaBundle:Party')->findAllAdminParties($email);

        if (count($adminOf) == 0 && count($participatingIn) == 0) {
            return false;
        }

        $manageLinks = [];
        foreach ($adminOf as $result) {
            $date = '';
            if ($result['eventdate'] instanceof \DateTime) {
                $date = $result['eventdate']->format('d/m/Y');
            }
            $manageLinks[] = [
                'url' => $this->routing->generate('party_manage', ['listurl' => $result['listurl']], Router::ABSOLUTE_URL),
                'date' => $date,
                'location' => $result['location'],
            ];
        }

        $participantLinks = [];
        foreach ($participatingIn as $participant) {
            $date = '';
            if ($participant->getParty()->getEventDate() instanceof \DateTime) {
                $date = $participant->getParty()->getEventDate()->format('d/m/Y');
            }

            $participantLinks[] = [
                'url' => $this->routing->generate('participant_view', ['url' => $participant->getUrl()], Router::ABSOLUTE_URL),
                'date' => $date,
                'location' => $participant->getParty()->getLocation(),
            ];
        }

        if (count($adminOf)) {
            $this->translator->setLocale($adminOf[0]['locale']);
        } else {
            $this->translator->setLocale($participatingIn[0]->getParty()->getLocale());
        }

        $message = (new \Swift_Message())
            ->setSubject($this->translator->trans('emails-forgot_link.subject'))
            ->setFrom($this->noreplyEmail, $this->translator->trans('emails-base_email.sender'))
            ->setTo($email)
            ->setBody(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:forgotLink.html.twig',
                    [
                        'manageLinks' => $manageLinks,
                        'participantLinks' => $participantLinks,
                    ]
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:forgotLink.txt.twig',
                    [
                        'manageLinks' => $manageLinks,
                        'participantLinks' => $participantLinks,
                    ]
                ),
                'text/plain'
            );
        $this->mailer->send($message);

        return true;
    }

    /**
     * @param $email
     *
     * @return bool
     */
    public function sendReuseLinksMail($email)
    {
        $results = $this->em->getRepository('IntractoSecretSantaBundle:Party')->findPartiesToReuse($email);

        if (count($results) == 0) {
            return false;
        }

        $partyLinks = [];
        foreach ($results as $result) {
            if ($result['eventdate'] instanceof \DateTime) {
                $text = $result['eventdate']->format('d/m/Y').' ';
            }
            $text .= $this->translator->trans('emails-reuse_link.at').' '.$result['location'];
            $partyLinks[] = [
                'url' => $this->routing->generate('party_reuse', ['listurl' => $result['listurl']], Router::ABSOLUTE_URL),
                'text' => $text,
            ];
        }

        $this->translator->setLocale($results[0]['locale']);

        $message = (new \Swift_Message())
            ->setSubject($this->translator->trans('emails-reuse_link.subject'))
            ->setFrom($this->noreplyEmail, $this->translator->trans('emails-base_email.sender'))
            ->setTo($email)
            ->setBody(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:reuseLink.html.twig',
                    [
                        'partyLinks' => $partyLinks,
                    ]
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:reuseLink.txt.twig',
                    [
                        'partyLinks' => $partyLinks,
                    ]
                ),
                'text/plain'
            );
        $this->mailer->send($message);

        return true;
    }

    /**
     * @param Party $party
     * @param $results
     */
    public function sendPartyUpdateMailForParty(Party $party, $results)
    {
        foreach ($party->getParticipants() as $participant) {
            if ($participant->isSubscribed()) {
                $this->sendPartyUpdateMailForParticipant($participant, $results);
            }
        }
    }

    /**
     * @param Participant $participant
     * @param $results
     */
    public function sendPartyUpdateMailForParticipant(Participant $participant, $results)
    {
        $this->translator->setLocale($participant->getParty()->getLocale());
        $mail = (new \Swift_Message())
            ->setSubject($this->translator->trans('emails-party_update.subject'))
            ->setFrom($this->noreplyEmail, $this->translator->trans('emails-base_email.sender'))
            ->setTo($participant->getEmail(), $participant->getName())
            ->setBody(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:partyUpdate.html.twig',
                    [
                        'participant' => $participant,
                        'results' => $results,
                    ]
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:partyUpdate.txt.twig',
                    [
                        'participant' => $participant,
                        'results' => $results,
                    ]
                ),
                'text/plain'
            );
        $mail->getHeaders()->addTextHeader('List-Unsubscribe', $this->unsubscribeService->getUnsubscribeLink($participant));
        $this->mailer->send($mail);
    }

    /**
     * @param Participant $participant
     */
    public function sendWishlistReminderMail(Participant $participant)
    {
        $this->translator->setLocale($participant->getParty()->getLocale());
        $mail = (new \Swift_Message())
            ->setSubject($this->translator->trans('emails-emptyWishlistReminder.subject'))
            ->setFrom($this->noreplyEmail, $this->translator->trans('emails-base_email.sender'))
            ->setTo($participant->getEmail(), $participant->getName())
            ->setBody(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:emptyWishlistReminder.html.twig',
                    [
                        'participant' => $participant,
                    ]
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:emptyWishlistReminder.txt.twig',
                    [
                        'participant' => $participant,
                    ]
                ),
                'text/plain'
            );
        $mail->getHeaders()->addTextHeader('List-Unsubscribe', $this->unsubscribeService->getUnsubscribeLink($participant));
        $this->mailer->send($mail);
    }

    /**
     * @param Participant $participant
     */
    public function sendParticipantViewReminderMail(Participant $participant)
    {
        $this->translator->setLocale($participant->getParty()->getLocale());
        $mail = (new \Swift_Message())
            ->setSubject($this->translator->trans('emails-viewParticipantReminder.subject'))
            ->setFrom($this->noreplyEmail, $this->translator->trans('emails-base_email.sender'))
            ->setTo($participant->getEmail(), $participant->getName())
            ->setBody(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:viewParticipantReminder.html.twig',
                    [
                        'participant' => $participant,
                    ]
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:viewParticipantReminder.txt.twig',
                    [
                        'participant' => $participant,
                    ]
                ),
                'text/plain'
            );
        $mail->getHeaders()->addTextHeader('List-Unsubscribe', $this->unsubscribeService->getUnsubscribeLink($participant));
        $this->mailer->send($mail);
    }

    /**
     * @param Participant $receiver
     * @param Participant $participant
     */
    public function sendWishlistUpdatedMail(Participant $receiver, Participant $participant)
    {
        $this->translator->setLocale($receiver->getParty()->getLocale());
        $mail = (new \Swift_Message())
            ->setSubject($this->translator->trans('emails-wishlistChanged.subject'))
            ->setFrom($this->noreplyEmail, $this->translator->trans('emails-base_email.sender'))
            ->setTo($participant->getEmail(), $participant->getName())
            ->setBody(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:wishlistChanged.html.twig',
                    [
                        'participant' => $receiver,
                        'secret_santa' => $participant,
                    ]
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:wishlistChanged.txt.twig',
                    [
                        'participant' => $receiver,
                        'secret_santa' => $participant,
                    ]
                ),
                'text/plain'
            );
        $mail->getHeaders()->addTextHeader('List-Unsubscribe', $this->unsubscribeService->getUnsubscribeLink($participant));
        $this->mailer->send($mail);
    }

    /**
     * @param Participant $participant
     */
    public function sendPartyStatusMail(Participant $participant)
    {
        $this->translator->setLocale($participant->getParty()->getLocale());
        $mail = (new \Swift_Message())
            ->setSubject($this->translator->trans('emails-party_status.subject'))
            ->setFrom($this->noreplyEmail, $this->translator->trans('emails-base_email.sender'))
            ->setTo($participant->getEmail(), $participant->getName())
            ->setBody(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:partyStatus.html.twig',
                    [
                        'party' => $participant->getParty(),
                    ]
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:partyStatus.txt.twig',
                    [
                        'party' => $participant->getParty(),
                        'admin' => $participant,
                    ]
                ),
                'text/plain'
            );
        $mail->getHeaders()->addTextHeader('List-Unsubscribe', $this->unsubscribeService->getUnsubscribeLink($participant));
        $this->mailer->send($mail);
    }

    /**
     * @param Party $party
     */
    public function sendPartyUpdatedMailsForParty(Party $party)
    {
        foreach ($party->getParticipants() as $participant) {
            if ($participant->isSubscribed()) {
                $this->sendPartyUpdatedMailForParticipant($participant);
            }
        }
    }

    /**
     * @param Participant $participant
     */
    public function sendPartyUpdatedMailForParticipant(Participant $participant)
    {
        $this->translator->setLocale($participant->getParty()->getLocale());
        $mail = (new \Swift_Message())
            ->setSubject($this->translator->trans('emails-updated_party.subject'))
            ->setFrom($this->noreplyEmail, $this->translator->trans('emails-base_email.sender'))
            ->setTo($participant->getEmail(), $participant->getName())
            ->setBody(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:updatedParty.html.twig',
                    [
                        'participant' => $participant,
                    ]
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:updatedParty.txt.twig',
                    [
                        'participant' => $participant,
                    ]
                ),
                'text/plain'
            );
        $mail->getHeaders()->addTextHeader('List-Unsubscribe', $this->unsubscribeService->getUnsubscribeLink($participant));
        $this->mailer->send($mail);
    }

    public function sendRemovedSecretSantaMail(Participant $participant)
    {
        $this->translator->setLocale($participant->getParty()->getLocale());
        $mail = (new \Swift_Message())
            ->setSubject($this->translator->trans('emails-removed_secret_santa.subject'))
            ->setFrom($this->noreplyEmail, $this->translator->trans('emails-base_email.sender'))
            ->setTo($participant->getEmail(), $participant->getName())
            ->setBody(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:removedSecretSanta.html.twig',
                    [
                        'participant' => $participant,
                    ]
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:removedSecretSanta.txt.twig',
                    [
                        'participant' => $participant,
                    ]
                ),
                'text/plain'
            );
        $mail->getHeaders()->addTextHeader('List-Unsubscribe', $this->unsubscribeService->getUnsubscribeLink($participant));
        $this->mailer->send($mail);
    }

    /**
     * @param $recipient
     * @param $message
     */
    public function sendAnonymousMessage(Participant $recipient, $message)
    {
        $this->translator->setLocale($recipient->getParty()->getLocale());

        $mail = (new \Swift_Message())
            ->setSubject($this->translator->trans('emails-anonymous_message.subject'))
            ->setFrom($this->noreplyEmail, $this->translator->trans('emails-base_email.sender'))
            ->setTo($recipient->getEmail())
            ->setBody(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:anonymousMessage.html.twig',
                    [
                        'message' => $message,
                        'participant' => $recipient,
                    ]
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'IntractoSecretSantaBundle:Emails:anonymousMessage.txt.twig',
                    [
                        'message' => $message,
                        'participant' => $recipient,
                    ]
                ),
                'text/plain'
            );
        $mail->getHeaders()->addTextHeader('List-Unsubscribe', $this->unsubscribeService->getUnsubscribeLink($recipient));
        $this->mailer->send($mail);
    }
}
