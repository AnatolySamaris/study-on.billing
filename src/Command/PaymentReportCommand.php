<?php

namespace App\Command;

use App\Repository\TransactionRepository;
use App\Service\Twig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'payment:report',
    description: 'Generates monthly payment report'
)]
class PaymentReportCommand extends Command
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private MailerInterface $mailer,
        private Twig $twig,
        #[Autowire('%app.report_email%')]
        private string $reportEmail
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // $startDate = new \DateTime('first day of this month');
        $endDate = new \DateTime('last day of this month');
        $startDate = new \DateTime('first day of previous month');
        // $endDate = new \DateTime('last day of previous month');
        
        $reportData = $this->transactionRepository->getMonthlyReportData($startDate, $endDate);
        
        $html = $this->twig->render('email/monthly_report.html.twig', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'reportData' => $reportData,
        ]);
        
        $email = (new Email())
            ->from('no-reply@study-on.local')
            ->to($this->reportEmail)
            ->subject('Payment Report for ' . $startDate->format('m.Y'))
            ->html($html);
        
        $this->mailer->send($email);
        
        $output->writeln('Report sent to ' . $this->reportEmail);
        
        return Command::SUCCESS;
    }
}
