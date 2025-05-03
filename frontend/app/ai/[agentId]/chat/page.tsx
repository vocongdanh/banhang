"use client"

import { useState, useEffect, useRef, memo } from "react"
import { useParams, useRouter } from "next/navigation"
import { AppShell } from "@/components/layout/app-shell"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Card, CardContent } from "@/components/ui/card"
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip"
import { ScrollArea } from "@/components/ui/scroll-area"
import { ArrowLeftIcon, SendIcon, MicIcon, ImageIcon, PaperclipIcon, RefreshCwIcon, Loader2Icon } from "lucide-react"
import { useToast } from "@/components/ui/use-toast"
import Link from "next/link"
import Image from "next/image"
import { getBusinessId } from "@/lib/api-client"
import ReactMarkdown from 'react-markdown'
import remarkGfm from 'remark-gfm'
import { Prism as SyntaxHighlighter } from 'react-syntax-highlighter'
import { tomorrow } from 'react-syntax-highlighter/dist/esm/styles/prism'
import type { Components } from 'react-markdown'
import type { PrismAsyncLight as SyntaxHighlighterType } from 'react-syntax-highlighter'
import dynamic from 'next/dynamic'

interface CodeProps {
  inline?: boolean
  className?: string
  children?: React.ReactNode
  node?: any // Added for react-markdown v6+ compatibility
}

interface Agent {
  id: string
  name: string
  avatar: string | null
  description: string | null
  capabilities: string[]
}

interface Message {
  id: string
  ai_conversation_id: string
  role: 'user' | 'assistant' | 'system'
  content: string
  files?: string[]
  is_read: boolean
  created_at: string
  loading?: boolean
}

interface Conversation {
  id: string
  title: string
  ai_agent_id: string
  user_id: string
  business_id: string
  last_message_at: string
  aiAgent: Agent
}

const markdownStyles = {
  p: 'mb-4 leading-relaxed',
  h1: 'text-3xl font-bold mb-4',
  h2: 'text-2xl font-bold mb-3',
  h3: 'text-xl font-bold mb-2',
  h4: 'text-lg font-bold mb-2',
  h5: 'text-base font-bold mb-2',
  h6: 'text-sm font-bold mb-2',
  ul: 'list-disc pl-6 mb-4',
  ol: 'list-decimal pl-6 mb-4',
  li: 'mb-1',
  code: 'bg-gray-100 rounded px-1 py-0.5 font-mono text-sm',
  pre: 'bg-gray-100 rounded-lg p-4 mb-4 overflow-x-auto',
  blockquote: 'border-l-4 border-gray-300 pl-4 italic my-4',
  a: 'text-blue-600 hover:underline',
  table: 'min-w-full border-collapse mb-4',
  thead: 'bg-gray-100',
  th: 'border border-gray-300 px-4 py-2 text-left',
  td: 'border border-gray-300 px-4 py-2',
};

const markdownComponents: Components = {
  p: ({ children }) => <p className={markdownStyles.p}>{children}</p>,
  h1: ({ children }) => <h1 className={markdownStyles.h1}>{children}</h1>,
  h2: ({ children }) => <h2 className={markdownStyles.h2}>{children}</h2>,
  h3: ({ children }) => <h3 className={markdownStyles.h3}>{children}</h3>,
  h4: ({ children }) => <h4 className={markdownStyles.h4}>{children}</h4>,
  h5: ({ children }) => <h5 className={markdownStyles.h5}>{children}</h5>,
  h6: ({ children }) => <h6 className={markdownStyles.h6}>{children}</h6>,
  ul: ({ children }) => <ul className={markdownStyles.ul}>{children}</ul>,
  ol: ({ children }) => <ol className={markdownStyles.ol}>{children}</ol>,
  li: ({ children }) => <li className={markdownStyles.li}>{children}</li>,
  code: ({ node, inline, className, children, ...props }: CodeProps) => {
    const match = /language-(\w+)/.exec(className || '');
    return !inline && match ? (
      <SyntaxHighlighter
        style={tomorrow as any}
        language={match[1]}
        PreTag="div"
        {...props as any}
      >
        {String(children).replace(/\n$/, '')}
      </SyntaxHighlighter>
    ) : (
      <code className={markdownStyles.code} {...props}>
        {children}
      </code>
    );
  },
  pre: ({ children }) => <pre className={markdownStyles.pre}>{children}</pre>,
  blockquote: ({ children }) => (
    <blockquote className={markdownStyles.blockquote}>{children}</blockquote>
  ),
  a: ({ href, children }) => (
    <a href={href} className={markdownStyles.a} target="_blank" rel="noopener noreferrer">
      {children}
    </a>
  ),
  table: ({ children }) => <table className={markdownStyles.table}>{children}</table>,
  thead: ({ children }) => <thead className={markdownStyles.thead}>{children}</thead>,
  th: ({ children }) => <th className={markdownStyles.th}>{children}</th>,
  td: ({ children }) => <td className={markdownStyles.td}>{children}</td>,
};

// Đưa việc dynamic import ra bên ngoài component
const MarkdownChartExtractor = dynamic(
  () => import('@/components/chart/MarkdownChartExtractor'),
  { 
    ssr: false,
    loading: () => <div className="p-4 text-gray-500">Đang tải biểu đồ...</div>
  }
);

// Tạo một component riêng biệt cho tin nhắn và sử dụng memo để tránh re-render
const MessageContent = memo(({ content }: { content: string }) => {
  return <MarkdownContent content={content} />;
});

MessageContent.displayName = 'MessageContent';

// Component wrapper cho ReactMarkdown
const MarkdownContent = ({ content }: { content: string }) => {
  // Kiểm tra xem nội dung có chứa biểu đồ không
  const hasMermaidOrChart = /```(mermaid|chart)\n/g.test(content);

  // Nếu có biểu đồ, sử dụng MarkdownChartExtractor
  if (hasMermaidOrChart) {
    return (
      <div className="markdown-content w-full overflow-hidden">
        <MarkdownChartExtractor 
          content={content} 
          markdownComponents={markdownComponents} 
        />
      </div>
    );
  }
  
  // Nếu không có biểu đồ, sử dụng ReactMarkdown thông thường
  return (
    <div className="markdown-content w-full overflow-hidden">
      <ReactMarkdown 
        remarkPlugins={[remarkGfm]}
        components={markdownComponents}
      >
        {content}
      </ReactMarkdown>
    </div>
  );
};

// Tạo component MessageInput riêng biệt với memo
const MessageInput = memo(({ 
  inputMessage,
  setInputMessage,
  handleKeyDown,
  sendMessage,
  handleFileClick,
  isSending,
  isLoading,
  files,
  agent,
  fileInputRef,
  handleFileChange
}: { 
  inputMessage: string;
  setInputMessage: (value: string) => void;
  handleKeyDown: (e: React.KeyboardEvent) => void;
  sendMessage: () => void;
  handleFileClick: () => void;
  isSending: boolean;
  isLoading: boolean;
  files: File[];
  agent: Agent | null;
  fileInputRef: React.RefObject<HTMLInputElement>;
  handleFileChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
}) => {
  return (
    <div className="border-t p-2 md:p-4">
      <div className="relative flex">
        <input 
          type="file" 
          ref={fileInputRef}
          onChange={handleFileChange}
          className="hidden"
          multiple
        />
        
        <Input
          placeholder="Type a message..."
          value={inputMessage}
          onChange={(e) => setInputMessage(e.target.value)}
          onKeyDown={handleKeyDown}
          disabled={isSending || isLoading}
          className="pr-20 md:pr-24 text-sm md:text-base"
        />
        
        <div className="absolute right-1 top-1 flex items-center gap-1">
          {agent?.capabilities?.includes('image_upload') && (
            <TooltipProvider>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button 
                    type="button" 
                    size="icon" 
                    variant="ghost" 
                    className="h-7 w-7 md:h-8 md:w-8"
                    onClick={handleFileClick}
                    disabled={isSending}
                  >
                    <ImageIcon className="h-3 w-3 md:h-4 md:w-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>
                  <p>Upload images</p>
                </TooltipContent>
              </Tooltip>
            </TooltipProvider>
          )}
          
          {agent?.capabilities?.includes('voice') && (
            <TooltipProvider>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button 
                    type="button" 
                    size="icon" 
                    variant="ghost" 
                    className="h-7 w-7 md:h-8 md:w-8"
                    disabled={true} // Voice feature not yet implemented
                  >
                    <MicIcon className="h-3 w-3 md:h-4 md:w-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>
                  <p>Voice message (coming soon)</p>
                </TooltipContent>
              </Tooltip>
            </TooltipProvider>
          )}
          
          <Button 
            type="button" 
            size="icon" 
            className="h-7 w-7 md:h-8 md:w-8"
            onClick={sendMessage}
            disabled={isSending || (inputMessage.trim() === '' && files.length === 0)}
          >
            {isSending ? (
              <Loader2Icon className="h-3 w-3 md:h-4 md:w-4 animate-spin" />
            ) : (
              <SendIcon className="h-3 w-3 md:h-4 md:w-4" />
            )}
          </Button>
        </div>
      </div>
    </div>
  );
});

MessageInput.displayName = 'MessageInput';

export default function ChatPage() {
  const params = useParams()
  const router = useRouter()
  const { toast } = useToast()
  
  const [agent, setAgent] = useState<Agent | null>(null)
  const [conversation, setConversation] = useState<Conversation | null>(null)
  const [messages, setMessages] = useState<Message[]>([])
  const [inputMessage, setInputMessage] = useState("")
  const [isLoading, setIsLoading] = useState(true)
  const [isSending, setIsSending] = useState(false)
  const [isNewConversation, setIsNewConversation] = useState(false)
  const [isLoggedIn, setIsLoggedIn] = useState(false)
  const [businessId, setBusinessId] = useState<string | null>(null)
  const [files, setFiles] = useState<File[]>([])
  
  const messagesEndRef = useRef<HTMLDivElement>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  
  const agentId = params?.agentId as string
  // Get conversationId from query params if it exists
  const searchParams = new URLSearchParams(window.location.search)
  const conversationIdFromUrl = searchParams.get('conversationId')
  
  if (!params?.agentId) {
    return <div>Agent ID không hợp lệ</div>
  }

  useEffect(() => {
    // Check for login and get business ID
    const token = localStorage.getItem('auth_token')
    setIsLoggedIn(!!token)
    
    const currentBusinessId = getBusinessId()
    if (currentBusinessId) {
      setBusinessId(currentBusinessId)
    }
  }, [])

  useEffect(() => {
    if (isLoggedIn && businessId && agentId) {
      console.log("Fetching agent details and conversation with:", {
        isLoggedIn,
        businessId,
        agentId
      });
      fetchAgentDetails();
      fetchOrCreateConversation();
    }
  }, [isLoggedIn, businessId, agentId]);

  useEffect(() => {
    // Scroll to bottom when messages change
    scrollToBottom()
  }, [messages])

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" })
  }

  // Disable eslint warning for missing dependencies
  // eslint-disable-next-line react-hooks/exhaustive-deps
  const fetchAgentDetails = async () => {
    try {
      const token = localStorage.getItem('auth_token')
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8001/api'
      
      console.log("Fetching agent details:", {
        agentId,
        apiUrl: `${apiUrl}/ai-agents/${agentId}`
      });
      
      const response = await fetch(`${apiUrl}/ai-agents/${agentId}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        mode: 'cors',
        credentials: 'include'
      })
      
      console.log("Agent details response status:", response.status);
      
      if (!response.ok) {
        throw new Error('Failed to fetch agent details')
      }
      
      const data = await response.json()
      console.log("Agent data received:", data);
      setAgent(data.data)
    } catch (error) {
      console.error('Error fetching agent details:', error)
      toast({
        title: "Error",
        description: "Failed to load AI agent. Please try again.",
        variant: "destructive"
      })
    }
  }

  const fetchOrCreateConversation = async () => {
    setIsLoading(true)
    try {
      const token = localStorage.getItem('auth_token')
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8001/api'
      
      // If we have a conversationId from URL, load that specific conversation
      if (conversationIdFromUrl) {
        console.log("Loading specific conversation from URL:", conversationIdFromUrl);
        
        try {
          const response = await fetch(`${apiUrl}/ai-conversations/${conversationIdFromUrl}`, {
            headers: {
              'Authorization': `Bearer ${token}`,
              'Content-Type': 'application/json',
              'Accept': 'application/json'
            },
            mode: 'cors',
            credentials: 'include'
          });
          
          if (!response.ok) {
            throw new Error('Failed to fetch conversation');
          }
          
          const data = await response.json();
          console.log("Conversation data:", data);
          
          setConversation(data.conversation);
          setMessages(data.messages || []);
          setIsNewConversation(false);
          setIsLoading(false);
          return;
        } catch (error) {
          console.error('Error fetching specific conversation:', error);
          toast({
            title: "Error",
            description: "Failed to load the specific conversation. Loading default conversation instead.",
            variant: "destructive"
          });
          // Continue with normal flow to find/create a conversation
        }
      }
      
      console.log("Fetching existing conversations:", {
        agentId,
        businessId,
        url: `${apiUrl}/ai-conversations?business_id=${businessId}`
      });
      
      // Try to find an existing conversation with this agent
      const responseExisting = await fetch(`${apiUrl}/ai-conversations?business_id=${businessId}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        mode: 'cors',
        credentials: 'include'
      })
      
      console.log("Existing conversations response status:", responseExisting.status);
      
      if (!responseExisting.ok) {
        throw new Error('Failed to fetch conversations')
      }
      
      const existingData = await responseExisting.json()
      console.log("Existing conversations:", existingData);
      
      // If there's an existing conversation, use it
      if (existingData.data && existingData.data.length > 0) {
        // Lọc cuộc trò chuyện với agent hiện tại
        const agentConversations = existingData.data.filter(
          (conv: any) => conv.ai_agent_id === agentId
        );
        console.log("Agent conversations:", agentConversations);
        
        if (agentConversations.length > 0) {
          const conv = agentConversations[0]; // Lấy cuộc trò chuyện gần nhất
          setConversation(conv)
          await fetchMessages(conv.id)
          setIsNewConversation(false)
        } else {
          // Không tìm thấy cuộc trò chuyện với agent này
          setIsNewConversation(true)
          setMessages([])
        }
      } else {
        // Otherwise create a new conversation
        setIsNewConversation(true)
        setMessages([])
      }
    } catch (error) {
      console.error('Error fetching/creating conversation:', error)
      toast({
        title: "Error",
        description: "Failed to load conversation. Please try again.",
        variant: "destructive"
      })
    } finally {
      setIsLoading(false)
    }
  }

  const fetchMessages = async (conversationId: string) => {
    try {
      const token = localStorage.getItem('auth_token')
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8001/api'
      
      console.log("Fetching messages for conversation:", conversationId);
      
      const response = await fetch(`${apiUrl}/ai-conversations/${conversationId}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        mode: 'cors',
        credentials: 'include'
      })
      
      console.log("Messages response status:", response.status);
      
      if (!response.ok) {
        throw new Error('Failed to fetch messages')
      }
      
      const data = await response.json()
      console.log("Messages data:", data);
      
      // API có thể trả về cấu trúc khác nhau
      if (data.messages) {
        setMessages(data.messages)
      } else if (data.data && data.data.messages) {
        setMessages(data.data.messages)
      } else {
        console.error("Unexpected message data structure:", data);
        setMessages([])
      }
    } catch (error) {
      console.error('Error fetching messages:', error)
      toast({
        title: "Error",
        description: "Failed to load messages. Please try again.",
        variant: "destructive"
      })
    }
  }

  const createNewConversation = async (initialMessage: string) => {
    try {
      const token = localStorage.getItem('auth_token')
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8001/api'
      
      console.log("Creating new conversation with:", {
        agentId,
        businessId,
        initialMessage,
        apiUrl
      });
      
      const fullUrl = `${apiUrl}/ai-conversations`;
      console.log("POST request to:", fullUrl);
      
      const response = await fetch(fullUrl, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          ai_agent_id: agentId,
          business_id: businessId,
          initial_message: initialMessage
        }),
        mode: 'cors',
        credentials: 'include'
      })
      
      console.log("Create conversation response status:", response.status);
      
      if (!response.ok) {
        const errorText = await response.text();
        console.error("Error creating conversation:", errorText);
        throw new Error('Failed to create conversation')
      }
      
      const data = await response.json()
      console.log("Conversation created successfully:", data);
      
      let conversationData;
      if (data.data) {
        conversationData = data.data;
      } else {
        conversationData = data;
      }
      
      setConversation(conversationData);
      
      if (conversationData.id) {
        await fetchMessages(conversationData.id);
        setIsNewConversation(false);
        // Clear input message after creating conversation
        setInputMessage("");
        return conversationData.id;
      }
      
      return null;
    } catch (error) {
      console.error('Error creating conversation:', error)
      toast({
        title: "Error",
        description: "Failed to create conversation. Please try again.",
        variant: "destructive"
      })
      return null
    }
  }

  const sendMessage = async () => {
    if (!inputMessage.trim() && files.length === 0) return
    
    setIsSending(true)
    
    try {
      const token = localStorage.getItem('auth_token')
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8001/api'
      let conversationId = conversation?.id
      
      // Check if we have a conversation or need to create one
      if (!conversationId) {
        await createNewConversation(inputMessage)
        
        if (!conversationId) {
          throw new Error('Failed to create conversation')
        }
        
        // Reset input after creating conversation
        setInputMessage("")
        setFiles([])
        setIsSending(false)
        return
      }
      
      // Create a temporary user message to display immediately
      const userMessage: Message = {
        id: `temp-${Date.now()}`,
        ai_conversation_id: conversationId,
        role: 'user',
        content: inputMessage,
        is_read: true,
        created_at: new Date().toISOString()
      }
      
      // Create a temporary assistant message with loading state
      const tempAssistantMessage: Message = {
        id: `temp-response-${Date.now()}`,
        ai_conversation_id: conversationId,
        role: 'assistant',
        content: '',
        is_read: false,
        created_at: new Date().toISOString(),
        loading: true
      }
      
      // Add temporary messages to the UI
      setMessages(prev => [...prev, userMessage, tempAssistantMessage])
      
      // Prepare form data for the request
      const formData = new FormData()
      formData.append('content', inputMessage)
      
      // Add files if any
      if (files.length > 0) {
        console.log("Adding files to request:", files.length);
        files.forEach(file => {
          formData.append('files[]', file)
        })
      }
      
      // Clear input and files
      setInputMessage("")
      setFiles([])
      
      // Step 1: First send the message to the stream-prepare endpoint to get a token
      const prepareResponse = await fetch(`${apiUrl}/ai-conversations/${conversationId}/messages/stream-prepare`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          content: inputMessage
        })
      });
      
      if (!prepareResponse.ok) {
        throw new Error('Failed to prepare streaming session');
      }
      
      const prepareData = await prepareResponse.json();
      const streamToken = prepareData.stream_token;
      
      if (!streamToken) {
        throw new Error('No stream token received');
      }
      
      // Step 2: Use the stream token to connect to the streaming endpoint
      const streamUrl = `${apiUrl}/ai-direct-stream/${streamToken}`;
      console.log("Connecting to streaming URL:", streamUrl);
      
      // Create Event Source for SSE (Server-Sent Events)
      const eventSource = new EventSource(streamUrl);
      console.log("EventSource created");
      
      // Handle connection open
      eventSource.onopen = (event) => {
        console.log("EventSource connection opened:", event);
      };
      
      let realUserMessageId = prepareData.message?.id || '';
      let realAssistantMessageId = '';
      
      // Set the user message with the actual ID from the server
      if (prepareData.message) {
        setMessages(prev => prev.map(msg => 
          msg.id === userMessage.id ? {...prepareData.message} : msg
        ));
      }
      
      // Handle stream events
      eventSource.onmessage = (event) => {
        console.log("Stream event received:", event.data);
        
        if (event.data === "[DONE]") {
          console.log("Stream complete, closing connection");
          eventSource.close();
          // Cập nhật trạng thái loading khi hoàn thành
          setMessages(prev => prev.map(msg => 
            msg.id === tempAssistantMessage.id 
              ? {...msg, loading: false} 
              : msg
          ));
          setIsSending(false);
          return;
        }
        
        try {
          const data = JSON.parse(event.data);
          console.log("Parsed stream data:", data);
          
          // Handle data from direct streaming endpoint
          if (data.type === 'chunk') {
            console.log("Text chunk received:", data.content);
            // Hiển thị ngay lập tức từng chunk text
            setMessages(prev => {
              const updatedMessages = prev.map(msg => {
                if (msg.id === tempAssistantMessage.id) {
                  return {
                    ...msg,
                    content: msg.content + data.content
                  };
                }
                return msg;
              });
              return updatedMessages;
            });
            return;
          }
          
          // Handle completion event from Assistants API
          if (data.type === 'complete') {
            console.log("Streaming complete, waiting for message ID");
            // We'll keep the message as is until we get the message_saved event
            return;
          }
          
          // Handle saved message with ID from the backend
          if (data.type === 'message_saved' && data.message_id) {
            console.log("Message saved with ID:", data.message_id);
            // Update our temporary message with the real one from the database
            setMessages(prev => prev.map(msg => 
              msg.id === tempAssistantMessage.id 
                ? {...msg, id: data.message_id, loading: false} 
                : msg
            ));
            setIsSending(false);
            return;
          }
          
          // Handle error event
          if (data.type === 'error') {
            console.error("Error received:", data.message || data.error);
            toast({
              title: "Error",
              description: data.message || data.error || "An error occurred while processing your message.",
              variant: "destructive"
            });
            
            // Update the assistant message to show error
            setMessages(prev => prev.map(msg => 
              msg.id === tempAssistantMessage.id 
                ? {...msg, content: data.message || "Xin lỗi, đã xảy ra lỗi khi xử lý tin nhắn.", loading: false} 
                : msg
            ));
            setIsSending(false);
            return;
          }
        } catch (err) {
          console.error("Error processing stream event:", err);
        }
      };
      
      eventSource.onerror = (error) => {
        console.error("EventSource error:", error);
        
        // Check if the error is due to the user not being authenticated
        if (error instanceof Event && (error as any).target?.readyState === EventSource.CLOSED) {
          console.error("EventSource connection closed");
        }
        
        eventSource.close();
        setIsSending(false);
        
        toast({
          title: "Connection Error",
          description: "Could not establish streaming connection. Please try again.",
          variant: "destructive"
        });
        
        // Update the assistant message to show error
        setMessages(prev => prev.map(msg => 
          msg.id === tempAssistantMessage.id 
            ? {...msg, content: "Không thể kết nối tới máy chủ. Vui lòng thử lại sau hoặc đăng nhập lại.", loading: false} 
            : msg
        ));
      };
    } catch (error) {
      console.error('Error sending message:', error)
      setIsSending(false)
      
      toast({
        title: "Error",
        description: "Failed to send message. Please try again.",
        variant: "destructive"
      })
    }
  }

  const handleKeyDown = (e: React.KeyboardEvent) => {
    console.log("Key down event:", e.key);
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      console.log("Enter key pressed (without shift)")
      sendMessage()
    }
  }

  const handleFileClick = () => {
    console.log("File button clicked");
    fileInputRef.current?.click()
  }

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    console.log("File input changed");
    if (e.target.files) {
      const newFiles = Array.from(e.target.files)
      console.log("Selected files:", newFiles.map(f => f.name));
      setFiles(prev => [...prev, ...newFiles])
    }
  }

  const removeFile = (index: number) => {
    console.log("Removing file at index:", index);
    setFiles(prev => prev.filter((_, i) => i !== index))
  }

  const formatTime = (dateString: string) => {
    const date = new Date(dateString)
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  }

  if (!isLoggedIn) {
    return (
      <AppShell>
        <div className="container mx-auto py-6">
          <div className="p-8 bg-yellow-50 border border-yellow-300 rounded-md">
            <h3 className="text-lg font-semibold mb-2">Bạn cần đăng nhập trước</h3>
            <p>Vui lòng <a href="/login" className="text-blue-600 hover:underline">đăng nhập</a> để sử dụng tính năng này.</p>
          </div>
        </div>
      </AppShell>
    )
  }

  if (!businessId) {
    return (
      <AppShell>
        <div className="container mx-auto py-6">
          <div className="p-8 bg-yellow-50 border border-yellow-300 rounded-md">
            <h3 className="text-lg font-semibold mb-2">Chọn doanh nghiệp</h3>
            <p>Vui lòng chọn một doanh nghiệp để chat với AI Agent.</p>
          </div>
        </div>
      </AppShell>
    )
  }

  return (
    <AppShell>
      <style jsx global>{`
        .typing-indicator {
          display: flex;
          align-items: center;
        }
        
        .typing-indicator span {
          height: 8px;
          width: 8px;
          background: currentColor;
          opacity: 0.6;
          border-radius: 50%;
          margin: 0 2px;
          animation: bounce 1.5s infinite ease-in-out;
        }
        
        .typing-indicator span:nth-child(1) {
          animation-delay: 0s;
        }
        
        .typing-indicator span:nth-child(2) {
          animation-delay: 0.2s;
        }
        
        .typing-indicator span:nth-child(3) {
          animation-delay: 0.4s;
        }
        
        @keyframes bounce {
          0%, 60%, 100% {
            transform: translateY(0);
          }
          30% {
            transform: translateY(-4px);
          }
        }
        ${markdownStyles}
      `}</style>
      
      <div className="flex flex-col h-[calc(100vh-4rem)]">
        {/* Chat header */}
        <div className="border-b p-2 md:p-4 flex justify-between items-center">
          <div className="flex items-center gap-2 md:gap-3">
            <Button variant="ghost" className="mr-1 p-1 md:p-2 h-8 w-8 md:h-9 md:w-9" asChild>
              <Link href="/ai">
                <ArrowLeftIcon className="h-4 w-4" />
              </Link>
            </Button>
            
            <Avatar className="h-8 w-8 md:h-9 md:w-9">
              {agent?.avatar ? (
                <AvatarImage 
                  src={agent.avatar.startsWith('http') 
                    ? agent.avatar 
                    : `${process.env.NEXT_PUBLIC_API_URL?.replace('/api', '')}/storage/${agent.avatar}`}
                  alt={agent?.name || 'AI Agent'}
                />
              ) : (
                <AvatarFallback>{agent?.name?.substring(0, 2).toUpperCase() || 'AI'}</AvatarFallback>
              )}
            </Avatar>
            
            <div>
              <h2 className="font-semibold text-xs md:text-sm">{agent?.name || 'AI Agent'}</h2>
              <p className="text-xs text-muted-foreground hidden sm:block">
                {conversation?.title || 'New conversation'}
              </p>
            </div>
          </div>
          
          {/* Debug info - hide on mobile */}
          <div className="text-xs text-muted-foreground hidden md:block">
            <p>API: {process.env.NEXT_PUBLIC_API_URL || 'Not set'}</p>
          </div>
          
          <TooltipProvider>
            <Tooltip>
              <TooltipTrigger asChild>
                <Button 
                  variant="ghost" 
                  size="icon" 
                  onClick={fetchOrCreateConversation}
                  disabled={isLoading}
                  className="h-8 w-8 md:h-9 md:w-9"
                >
                  <RefreshCwIcon className="h-4 w-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>
                <p>Start new conversation</p>
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>
        </div>
        
        {/* Chat messages */}
        <ScrollArea className="flex-1 p-2 md:p-4">
          {isLoading ? (
            <div className="flex justify-center items-center h-full">
              <Loader2Icon className="h-8 w-8 animate-spin text-muted-foreground" />
            </div>
          ) : messages.length === 0 ? (
            <div className="flex flex-col justify-center items-center h-full text-center px-4">
              <div className="bg-primary/10 p-4 rounded-full mb-4">
                <Avatar className="h-12 w-12">
                  {agent?.avatar ? (
                    <AvatarImage 
                      src={agent.avatar.startsWith('http') 
                        ? agent.avatar 
                        : `${process.env.NEXT_PUBLIC_API_URL?.replace('/api', '')}/storage/${agent.avatar}`}
                      alt={agent?.name || 'AI Agent'}
                    />
                  ) : (
                    <AvatarFallback>{agent?.name?.substring(0, 2).toUpperCase() || 'AI'}</AvatarFallback>
                  )}
                </Avatar>
              </div>
              <h3 className="text-base md:text-lg font-medium mb-2">Start chatting with {agent?.name}</h3>
              <p className="text-sm text-muted-foreground max-w-md mb-6">
                {agent?.description || 'Ask any questions or start a conversation.'}
              </p>
            </div>
          ) : (
            <div className="space-y-4 pb-4">
              {messages.filter(m => m.role !== 'system').map((message) => (
                <div 
                  key={message.id} 
                  className={`flex ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}
                >
                  <div className={`flex gap-2 md:gap-3 w-fit max-w-[85%] md:max-w-[80%] ${message.role === 'user' ? 'flex-row-reverse' : ''}`}>
                    <Avatar className="h-6 w-6 md:h-8 md:w-8 flex-shrink-0 mt-1">
                      {message.role === 'user' ? (
                        <AvatarFallback>Me</AvatarFallback>
                      ) : agent?.avatar ? (
                        <AvatarImage 
                          src={agent.avatar.startsWith('http') 
                            ? agent.avatar 
                            : `${process.env.NEXT_PUBLIC_API_URL?.replace('/api', '')}/storage/${agent.avatar}`}
                          alt={agent?.name || 'AI'}
                        />
                      ) : (
                        <AvatarFallback>{agent?.name?.substring(0, 2).toUpperCase() || 'AI'}</AvatarFallback>
                      )}
                    </Avatar>
                    
                    <div>
                      <Card className={`${message.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted'}`}>
                        <CardContent className="p-2 md:p-3 text-sm">
                          {message.loading ? (
                            <div>
                              <MessageContent content={message.content} />
                              <div className="mt-1 flex">
                                <div className="typing-indicator">
                                  <span></span>
                                  <span></span>
                                  <span></span>
                                </div>
                              </div>
                            </div>
                          ) : (
                            <MessageContent content={message.content} />
                          )}
                          
                          {/* Display files if any */}
                          {message.files && message.files.length > 0 && (
                            <div className="mt-2 grid gap-2">
                              {message.files.map((file, index) => (
                                <a 
                                  key={index} 
                                  href={file} 
                                  target="_blank" 
                                  rel="noopener noreferrer"
                                  className="flex items-center gap-2 p-2 rounded bg-background/50 hover:bg-background"
                                >
                                  <PaperclipIcon className="h-3 w-3 md:h-4 md:w-4" />
                                  <span className="text-xs truncate">
                                    {file.split('/').pop() || 'File'}
                                  </span>
                                </a>
                              ))}
                            </div>
                          )}
                        </CardContent>
                      </Card>
                      <div className={`text-[10px] md:text-xs text-muted-foreground mt-1 ${message.role === 'user' ? 'text-right' : ''}`}>
                        {formatTime(message.created_at)}
                      </div>
                    </div>
                  </div>
                </div>
              ))}
              <div ref={messagesEndRef} />
            </div>
          )}
        </ScrollArea>
        
        {/* Selected files */}
        {files.length > 0 && (
          <div className="px-2 md:px-4 pt-2">
            <div className="flex flex-wrap gap-2">
              {files.map((file, index) => (
                <div key={index} className="flex items-center gap-1 bg-muted p-1 rounded text-xs">
                  <span className="truncate max-w-[80px] md:max-w-[100px]">{file.name}</span>
                  <Button 
                    variant="ghost" 
                    size="icon" 
                    className="h-4 w-4 rounded-full"
                    onClick={() => removeFile(index)}
                  >
                    <span>×</span>
                  </Button>
                </div>
              ))}
            </div>
          </div>
        )}
        
        {/* Message input */}
        <MessageInput 
          inputMessage={inputMessage}
          setInputMessage={setInputMessage}
          handleKeyDown={handleKeyDown}
          sendMessage={sendMessage}
          handleFileClick={handleFileClick}
          isSending={isSending}
          isLoading={isLoading}
          files={files}
          agent={agent}
          fileInputRef={fileInputRef}
          handleFileChange={handleFileChange}
        />
      </div>
    </AppShell>
  )
} 