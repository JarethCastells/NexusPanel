import { Controller, Get, Param, Query } from '@nestjs/common';
import { ApiTags, ApiOperation, ApiResponse, ApiParam, ApiQuery } from '@nestjs/swagger';
import { ChatService } from './chat.service';

@ApiTags('chats')
@Controller('sessions/:sessionId/chats')
export class ChatController {
  constructor(private readonly chatService: ChatService) {}

  @Get()
  @ApiOperation({ summary: 'Get recent chats for a session' })
  @ApiParam({ name: 'sessionId', description: 'Session ID' })
  @ApiQuery({ name: 'limit', required: false, type: Number, description: 'Max chats to return' })
  @ApiResponse({ status: 200, description: 'List of chats' })
  async getChats(
    @Param('sessionId') sessionId: string,
    @Query('limit') limit?: string,
  ) {
    return this.chatService.getChats(sessionId, limit ? parseInt(limit, 10) : 50);
  }

  @Get(':chatId/messages')
  @ApiOperation({ summary: 'Get chat messages for a session' })
  @ApiParam({ name: 'sessionId', description: 'Session ID' })
  @ApiParam({ name: 'chatId', description: 'Chat ID' })
  @ApiQuery({ name: 'limit', required: false, type: Number, description: 'Max messages to return' })
  @ApiResponse({ status: 200, description: 'Chat messages' })
  async getChatMessages(
    @Param('sessionId') sessionId: string,
    @Param('chatId') chatId: string,
    @Query('limit') limit?: string,
  ) {
    return this.chatService.getChatMessages(sessionId, chatId, limit ? parseInt(limit, 10) : 50);
  }
}
