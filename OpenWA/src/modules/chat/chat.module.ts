import { Module, forwardRef } from '@nestjs/common';
import { SessionModule } from '../session/session.module';
import { ChatController } from './chat.controller';
import { ChatService } from './chat.service';

@Module({
  imports: [forwardRef(() => SessionModule)],
  controllers: [ChatController],
  providers: [ChatService],
  exports: [ChatService],
})
export class ChatModule {}
