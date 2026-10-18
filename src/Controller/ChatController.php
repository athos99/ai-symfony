<?php

namespace App\Controller;

use Symfony\AI\Agent\AgentInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ChatController extends AbstractController
{
    public function __construct(#[Target('default')] private AgentInterface $agent)
    {
    }

    #[Route('/chat', name: 'chat')]
    public function index(Request $request): Response
    {
        $response =$raw= null;
        $prompt = '';
        if ($request->isMethod('POST')) {
            $prompt = (string) $request->request->get('prompt', '');
            if ($prompt !== '') {
                $execution = $this->agent->call($prompt);
//                $raw=$execution->getRawResult();
                $response = $execution->getContent();
            }
        }

        return $this->render('chat.html.twig', [
            'prompt' => $prompt,
            'response' => $response,
        ]);
    }
}
