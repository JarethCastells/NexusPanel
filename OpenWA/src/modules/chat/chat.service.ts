import { Injectable, BadRequestException } from '@nestjs/common';
import { SessionService } from '../session/session.service';
import { IWhatsAppEngine } from '../../engine/interfaces/whatsapp-engine.interface';

@Injectable()
export class ChatService {
  constructor(private readonly sessionService: SessionService) {}

  async getChats(sessionId: string, limit = 50) {
    const engine = this.getEngine(sessionId);
    if (typeof engine.getRecentChats !== 'function') {
      throw new BadRequestException('Historic chat sync is not supported for this session.');
    }

    return engine.getRecentChats(limit);
  }

  async getChatMessages(sessionId: string, chatId: string, limit = 50) {
    const engine = this.getEngine(sessionId);
    if (typeof engine.fetchChatMessages !== 'function') {
      throw new BadRequestException('Historic chat message fetch is not supported for this session.');
    }

    const messages = await engine.fetchChatMessages(chatId, limit);
    return { messages };
  }

  private getEngine(sessionId: string): IWhatsAppEngine {
    const engine = this.sessionService.getEngine(sessionId);
    if (!engine) {
      throw new BadRequestException(`Session '${sessionId}' is not active. Start the session first.`);
    }
    return engine;
  }
}
