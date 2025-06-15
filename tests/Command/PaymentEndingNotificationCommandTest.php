<?php

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\MailerInterface;

class PaymentEndingNotificationCommandTest extends KernelTestCase
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
        $command = $application->find('payment:ending:notification');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        // Проверяем, что письма были отправлены (ожидается одно письмо для user@mail.ru)
        $this->assertEquals(1, count($sentEmails));

        // Проверяем, что письмо корректно
        $this->assertInstanceOf(\Symfony\Component\Mime\Email::class, $sentEmails[0]);
        $this->assertStringContainsString(
            'Notification About Courses Ending Soon',
            $sentEmails[0]->getSubject()
        );
        $this->assertEquals(
            'user@mail.ru',
            $sentEmails[0]->getTo()[0]->getAddress()
        );

        // Проверяем вывод команды
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Sent', $output);
    }
}
