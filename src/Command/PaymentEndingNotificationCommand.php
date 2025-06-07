<?php

namespace App\Command;

use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use App\Service\Twig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'payment:ending:notification',
    description: 'Sends notifications about courses ending soon'
)]
class PaymentEndingNotificationCommand extends Command
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private CourseRepository $courseRepository,
        private MailerInterface $mailer,
        private Twig $twig
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $endingCourses = $this->transactionRepository->findCoursesEndingSoon();
        
        $usersCourses = [];
        foreach ($endingCourses as $transaction) {
            $user = $transaction->getBillingUser();
            $course = $transaction->getCourse();
            
            if (!isset($usersCourses[$user->getId()])) {
                $usersCourses[$user->getId()] = [
                    'user' => $user,
                    'courses' => [],
                ];
            }
            
            $usersCourses[$user->getId()]['courses'][] = [
                'title' => $course->getTitle(),
                'expires_at' => $transaction->getExpiredAt(),
            ];
        }
        
        foreach ($usersCourses as $userData) {
            $html = $this->twig->render('email/ending_notification.html.twig', [
                'user' => $userData['user'],
                'courses' => $userData['courses'],
            ]);
            
            $email = (new Email())
                ->from('no-reply@study-on.local')
                ->to($userData['user']->getEmail())
                ->subject('Notification About Courses Ending Soon')
                ->html($html);
            
            $this->mailer->send($email);
        }
        
        $output->writeln(sprintf('Sent %d notifications', count($usersCourses)));
        
        return Command::SUCCESS;
    }
}
