<?php

namespace App\Service;

use App\Entity\Agent;
use App\Entity\Crew;
use App\Entity\CrewMember;
use App\Entity\Document;
use App\Entity\KnowledgeBase;
use App\Entity\User;
use App\Enum\CrewStrategy;
use App\Message\IngestDocument;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Demo workspace that walks the whole ladder: an app, tool-using agents, a RAG agent,
 * an autonomous agent and a crew. Credentials are documented in README.md.
 */
final class DemoSeeder
{
    public const ADMIN_EMAIL = 'admin@zyntara.local';
    public const DEMO_EMAIL = 'demo@zyntara.local';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly TemplateCatalog $templates,
        private readonly MessageBusInterface $bus,
        #[Autowire('%env(resolve:STORAGE_DIR)%')]
        private readonly string $storageDir,
    ) {
    }

    public function seed(): void
    {
        $admin = (new User())->setEmail(self::ADMIN_EMAIL)->setFullName('ZYNTARA Admin')->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'Admin#2026'));
        $demo = (new User())->setEmail(self::DEMO_EMAIL)->setFullName('Demo Builder');
        $demo->setPassword($this->hasher->hashPassword($demo, 'Demo#2026'));
        $this->em->persist($admin);
        $this->em->persist($demo);

        $kb = (new KnowledgeBase())->setName('Shop policies')->setDescription('Shipping, returns and warranty rules of the demo shop.')->setOwner($demo);
        $this->em->persist($kb);

        $agents = [];
        foreach ($this->templates->all() as $template) {
            $agent = (new Agent())
                ->setOwner($demo)
                ->setName($template['name'])
                ->setDescription($template['description'])
                ->setVertical($template['vertical'])
                ->setSystemPrompt($template['systemPrompt'])
                ->setTools($template['tools'])
                ->setAutonomous($template['autonomous']);
            if ('shop-support' === $template['id']) {
                $agent->addKnowledgeBase($kb)->setMemoryEnabled(true);
            }
            $this->em->persist($agent);
            $agents[$template['id']] = $agent;
        }

        $crew = (new Crew())
            ->setOwner($demo)
            ->setName('Launch brief crew')
            ->setDescription('Research, write code samples and review — three agents, one goal.')
            ->setGoal('Produce a reviewed one-page brief with a code sample for the requested topic.')
            ->setStrategy(CrewStrategy::Supervisor);
        foreach ([['research-agent', 'Researcher'], ['coder', 'Coder'], ['reviewer', 'Reviewer']] as $i => [$id, $role]) {
            $crew->addMember((new CrewMember())->setAgent($agents[$id])->setRole($role)->setPosition($i));
        }
        $this->em->persist($crew);

        $relative = 'kb/'.$kb->getId()->toRfc4122().'/shop-policies.md';
        (new Filesystem())->dumpFile($this->storageDir.'/'.$relative, <<<'MD'
            # ZYNTARA Demo Shop — Policies

            ## Shipping
            Orders ship within 2 business days. Standard delivery takes 3–5 business days and is free above 50 EUR.
            Express delivery (next business day) costs 9.90 EUR.

            ## Returns
            Products can be returned within 30 days of delivery, unused and in their original packaging.
            Refunds are issued to the original payment method within 5 business days after we receive the item.
            Personalised products cannot be returned.

            ## Warranty
            Electronics carry a 2-year warranty. Contact support with your order number to open a claim.
            MD);
        $document = new Document($kb, 'shop-policies.md', $relative, 'text/markdown', (int) filesize($this->storageDir.'/'.$relative));
        $this->em->persist($document);

        $this->em->flush();

        $this->bus->dispatch(new IngestDocument($document->getId()->toRfc4122()));
    }
}
