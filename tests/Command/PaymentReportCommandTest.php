<?php

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\MailerInterface;

class PaymentReportCommandTest extends KernelTestCase
{
    public function testCommandCorrect(): void
    {
        // Для перехвата писем
        $sentEmails = [];

        // Мок мейлера
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->method('send')
            ->willReturnCallback(function ($email) use (&$sentEmails) {
                $sentEmails[] = $email;
            });

        // Мокаем в контейнере
        self::bootKernel();
        $container = static::getContainer();
        $container->set(MailerInterface::class, $mailer);

        // Запуск команды
        $application = new Application(self::$kernel);
        $command = $application->find('payment:report');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        // Проверяем, что письмо были отправлены
        $this->assertEquals(1, count($sentEmails));

        // Проверяем, что письмо корректно
        $this->assertInstanceOf(\Symfony\Component\Mime\Email::class, $sentEmails[0]);
        $this->assertStringContainsString(
            'Payment Report for',
            $sentEmails[0]->getSubject()
        );
        $this->assertEquals(
            'reports@study-on.local',
            $sentEmails[0]->getTo()[0]->getAddress()
        );

        // Проверяем вывод команды
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Report sent', $output);
    }
}
