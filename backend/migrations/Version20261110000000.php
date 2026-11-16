<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial ZYNTARA schema (PostgreSQL). Vector tables (kb_chunks, agent_memories) and the
 * demo business tables are created by the agents service (see schema_filter in doctrine.yaml).
 */
final class Version20261110000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: users, agents, knowledge bases, documents, crews, conversations, runs, notifications';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration targets PostgreSQL.');

        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email VARCHAR(180) NOT NULL, full_name VARCHAR(120) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, active BOOLEAN DEFAULT true NOT NULL, preferences JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE TABLE knowledge_bases (id UUID NOT NULL, name VARCHAR(120) NOT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_A0EBC47E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_A0EBC47E3C61F9 ON knowledge_bases (owner_id)');
        $this->addSql('CREATE TABLE agents (id UUID NOT NULL, name VARCHAR(120) NOT NULL, description TEXT DEFAULT NULL, vertical VARCHAR(32) NOT NULL, system_prompt TEXT NOT NULL, provider VARCHAR(32) NOT NULL, model VARCHAR(120) NOT NULL, temperature DOUBLE PRECISION NOT NULL, tools JSON NOT NULL, autonomous BOOLEAN NOT NULL, max_iterations INTEGER NOT NULL, memory_enabled BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_9596AB6E7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_9596AB6E7E3C61F9 ON agents (owner_id)');
        $this->addSql('CREATE TABLE agent_knowledge_bases (agent_id UUID NOT NULL, knowledge_base_id UUID NOT NULL, PRIMARY KEY (agent_id, knowledge_base_id), CONSTRAINT FK_5E2B1B2C3414710B FOREIGN KEY (agent_id) REFERENCES agents (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5E2B1B2C1620F1CE FOREIGN KEY (knowledge_base_id) REFERENCES knowledge_bases (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_5E2B1B2C3414710B ON agent_knowledge_bases (agent_id)');
        $this->addSql('CREATE INDEX IDX_5E2B1B2C1620F1CE ON agent_knowledge_bases (knowledge_base_id)');
        $this->addSql('CREATE TABLE crews (id UUID NOT NULL, name VARCHAR(120) NOT NULL, description TEXT DEFAULT NULL, goal TEXT NOT NULL, strategy VARCHAR(16) NOT NULL, supervisor_provider VARCHAR(32) NOT NULL, supervisor_model VARCHAR(120) NOT NULL, max_rounds INTEGER NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_3EE854EB7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3EE854EB7E3C61F9 ON crews (owner_id)');
        $this->addSql('CREATE TABLE crew_members (id UUID NOT NULL, role VARCHAR(80) NOT NULL, position INTEGER NOT NULL, crew_id UUID NOT NULL, agent_id UUID NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_75911E665FE259F6 FOREIGN KEY (crew_id) REFERENCES crews (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_75911E663414710B FOREIGN KEY (agent_id) REFERENCES agents (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_75911E665FE259F6 ON crew_members (crew_id)');
        $this->addSql('CREATE INDEX IDX_75911E663414710B ON crew_members (agent_id)');
        $this->addSql('CREATE TABLE documents (id UUID NOT NULL, original_name VARCHAR(255) NOT NULL, stored_path VARCHAR(500) NOT NULL, mime_type VARCHAR(120) NOT NULL, size INTEGER NOT NULL, status VARCHAR(16) NOT NULL, chunk_count INTEGER NOT NULL, error TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, indexed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, knowledge_base_id UUID NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_A2B072881620F1CE FOREIGN KEY (knowledge_base_id) REFERENCES knowledge_bases (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_A2B072887B00651C ON documents (status)');
        $this->addSql('CREATE INDEX IDX_A2B072881620F1CE ON documents (knowledge_base_id)');
        $this->addSql('CREATE TABLE conversations (id UUID NOT NULL, title VARCHAR(160) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id UUID NOT NULL, agent_id UUID NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_C2521BF17E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C2521BF13414710B FOREIGN KEY (agent_id) REFERENCES agents (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C2521BF17E3C61F9 ON conversations (owner_id)');
        $this->addSql('CREATE INDEX IDX_C2521BF13414710B ON conversations (agent_id)');
        $this->addSql('CREATE TABLE runs (id UUID NOT NULL, kind VARCHAR(16) NOT NULL, input TEXT NOT NULL, provider VARCHAR(32) DEFAULT NULL, model VARCHAR(120) DEFAULT NULL, temperature DOUBLE PRECISION DEFAULT NULL, status VARCHAR(16) NOT NULL, output TEXT DEFAULT NULL, error TEXT DEFAULT NULL, tokens_in INTEGER NOT NULL, tokens_out INTEGER NOT NULL, duration_ms INTEGER DEFAULT NULL, tools_used JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, owner_id UUID NOT NULL, agent_id UUID DEFAULT NULL, crew_id UUID DEFAULT NULL, conversation_id UUID DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_803A7B1F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_803A7B1F3414710B FOREIGN KEY (agent_id) REFERENCES agents (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_803A7B1F5FE259F6 FOREIGN KEY (crew_id) REFERENCES crews (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_803A7B1F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_803A7B1F7B00651C ON runs (status)');
        $this->addSql('CREATE INDEX IDX_803A7B1F8B8E8428 ON runs (created_at)');
        $this->addSql('CREATE INDEX IDX_803A7B1F7E3C61F9 ON runs (owner_id)');
        $this->addSql('CREATE INDEX IDX_803A7B1F3414710B ON runs (agent_id)');
        $this->addSql('CREATE INDEX IDX_803A7B1F5FE259F6 ON runs (crew_id)');
        $this->addSql('CREATE INDEX IDX_803A7B1F9AC0396 ON runs (conversation_id)');
        $this->addSql('CREATE TABLE messages (id UUID NOT NULL, role VARCHAR(16) NOT NULL, content TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, conversation_id UUID NOT NULL, run_id UUID DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_DB021E969AC0396 FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_DB021E9684E3FEC4 FOREIGN KEY (run_id) REFERENCES runs (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_DB021E969AC0396 ON messages (conversation_id)');
        $this->addSql('CREATE INDEX IDX_DB021E9684E3FEC4 ON messages (run_id)');
        $this->addSql('CREATE TABLE notifications (id UUID NOT NULL, type VARCHAR(32) NOT NULL, title VARCHAR(200) NOT NULL, body TEXT DEFAULT NULL, link VARCHAR(255) DEFAULT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, recipient_id UUID NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_6000B0D3E92F8F78 FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6000B0D3E92F8F78E2DA3872 ON notifications (recipient_id, read_at)');
        $this->addSql('CREATE INDEX IDX_6000B0D3E92F8F78 ON notifications (recipient_id)');
        $this->addSql('CREATE TABLE run_steps (id UUID NOT NULL, sequence INTEGER NOT NULL, actor VARCHAR(120) NOT NULL, type VARCHAR(16) NOT NULL, content TEXT NOT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, run_id UUID NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_8BD10FC84E3FEC4 FOREIGN KEY (run_id) REFERENCES runs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8BD10FC84E3FEC45286D72B ON run_steps (run_id, sequence)');
        $this->addSql('CREATE INDEX IDX_8BD10FC84E3FEC4 ON run_steps (run_id)');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS run_steps CASCADE');
        $this->addSql('DROP TABLE IF EXISTS notifications CASCADE');
        $this->addSql('DROP TABLE IF EXISTS messages CASCADE');
        $this->addSql('DROP TABLE IF EXISTS runs CASCADE');
        $this->addSql('DROP TABLE IF EXISTS conversations CASCADE');
        $this->addSql('DROP TABLE IF EXISTS documents CASCADE');
        $this->addSql('DROP TABLE IF EXISTS crew_members CASCADE');
        $this->addSql('DROP TABLE IF EXISTS crews CASCADE');
        $this->addSql('DROP TABLE IF EXISTS agent_knowledge_bases CASCADE');
        $this->addSql('DROP TABLE IF EXISTS agents CASCADE');
        $this->addSql('DROP TABLE IF EXISTS knowledge_bases CASCADE');
        $this->addSql('DROP TABLE IF EXISTS users CASCADE');
        $this->addSql('DROP TABLE IF EXISTS messenger_messages CASCADE');
    }
}
