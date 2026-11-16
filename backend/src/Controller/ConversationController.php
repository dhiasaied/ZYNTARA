<?php

namespace App\Controller;

use App\Dto\PostMessageRequest;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\Run;
use App\Entity\User;
use App\Enum\RunKind;
use App\Service\RunManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/conversations', priority: 10)]
#[IsGranted('ROLE_USER')]
final class ConversationController extends AbstractController
{
    /** Sends a user message: stores it and starts an agent run that will post the reply. */
    #[Route('/{id}/messages', name: 'conversation_post_message', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function postMessage(
        string $id,
        #[CurrentUser] User $user,
        #[MapRequestPayload] PostMessageRequest $payload,
        EntityManagerInterface $em,
        RunManager $runManager,
    ): JsonResponse {
        $conversation = $em->find(Conversation::class, $id);
        if (null === $conversation || !$conversation->getOwner()?->getId()->equals($user->getId())) {
            throw $this->createNotFoundException('Conversation not found.');
        }
        $agent = $conversation->getAgent() ?? throw $this->createNotFoundException('The agent of this conversation was deleted.');

        if (0 === $conversation->getMessageCount() && 'New conversation' === $conversation->getTitle()) {
            $conversation->setTitle(mb_strimwidth(preg_replace('/\s+/', ' ', $payload->content) ?? '', 0, 60, '…'));
        }

        $run = (new Run())
            ->setKind(RunKind::Agent)
            ->setAgent($agent)
            ->setConversation($conversation)
            ->setInput($payload->content);
        $message = new Message($conversation, Message::ROLE_USER, $payload->content, $run);
        $em->persist($message);

        $runManager->enqueue($run, $user);

        return $this->json([
            'message' => [
                'id' => $message->getId()->toRfc4122(),
                'role' => $message->getRole(),
                'content' => $message->getContent(),
                'runId' => $run->getId()->toRfc4122(),
                'runStatus' => $run->getStatus()->value,
                'createdAt' => $message->getCreatedAt()->format(\DATE_ATOM),
            ],
            'run' => ['id' => $run->getId()->toRfc4122(), 'status' => $run->getStatus()->value],
            'title' => $conversation->getTitle(),
        ], Response::HTTP_ACCEPTED);
    }
}
