"use client"

import { useState, useEffect } from "react"
import { AppShell } from "@/components/layout/app-shell"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Skeleton } from "@/components/ui/skeleton"
import { ArrowLeftIcon, RefreshCwIcon, PlusIcon, SearchIcon } from "lucide-react"
import { useToast } from "@/components/ui/use-toast"
import { Input } from "@/components/ui/input"
import Link from "next/link"
import { getBusinessId } from "@/lib/api-client"

interface Agent {
  id: string
  name: string
  avatar: string | null
}

interface Conversation {
  id: string
  title: string
  ai_agent_id: string
  user_id: string
  business_id: string
  last_message_at: string
  aiAgent: Agent
  last_message?: {
    content: string
    role: 'user' | 'assistant'
  }
}

export default function ConversationsPage() {
  const { toast } = useToast()
  const [conversations, setConversations] = useState<Conversation[]>([])
  const [filteredConversations, setFilteredConversations] = useState<Conversation[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [isLoggedIn, setIsLoggedIn] = useState(false)
  const [businessId, setBusinessId] = useState<string | null>(null)
  const [searchQuery, setSearchQuery] = useState("")

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
    if (isLoggedIn && businessId) {
      fetchConversations()
    }
  }, [isLoggedIn, businessId])

  useEffect(() => {
    if (searchQuery) {
      const filtered = conversations.filter(conv => 
        conv.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (conv.aiAgent?.name || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
        (conv.last_message?.content || "").toLowerCase().includes(searchQuery.toLowerCase())
      )
      setFilteredConversations(filtered)
    } else {
      setFilteredConversations(conversations)
    }
  }, [searchQuery, conversations])

  const fetchConversations = async () => {
    setIsLoading(true)
    try {
      const token = localStorage.getItem('auth_token')
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8001/api'
      
      const response = await fetch(`${apiUrl}/ai-conversations?business_id=${businessId}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      })
      
      if (!response.ok) {
        throw new Error('Failed to fetch conversations')
      }
      
      const data = await response.json()
      setConversations(data.data)
      setFilteredConversations(data.data)
    } catch (error) {
      console.error('Error fetching conversations:', error)
      toast({
        title: "Error",
        description: "Failed to load conversations. Please try again.",
        variant: "destructive"
      })
    } finally {
      setIsLoading(false)
    }
  }

  const formatDateTime = (dateString: string) => {
    const date = new Date(dateString)
    const now = new Date()
    const yesterday = new Date(now)
    yesterday.setDate(now.getDate() - 1)
    
    // Today
    if (date.toDateString() === now.toDateString()) {
      return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    }
    
    // Yesterday
    if (date.toDateString() === yesterday.toDateString()) {
      return 'Yesterday'
    }
    
    // This year
    if (date.getFullYear() === now.getFullYear()) {
      return date.toLocaleDateString([], { month: 'short', day: 'numeric' })
    }
    
    // Previous years
    return date.toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' })
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
            <p>Vui lòng chọn một doanh nghiệp để xem các cuộc hội thoại.</p>
          </div>
        </div>
      </AppShell>
    )
  }

  return (
    <AppShell>
      <div className="container mx-auto py-6 space-y-6">
        <div className="flex justify-between items-center">
          <div className="flex items-center gap-4">
            <Button variant="ghost" className="p-2 h-9 w-9" asChild>
              <Link href="/ai">
                <ArrowLeftIcon className="h-4 w-4" />
              </Link>
            </Button>
            <h1 className="text-xl font-bold">Cuộc trò chuyện</h1>
          </div>
          
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={fetchConversations} disabled={isLoading}>
              <RefreshCwIcon className="h-4 w-4 mr-2" />
              Làm mới
            </Button>
            <Button variant="default" size="sm" asChild>
              <Link href="/ai">
                <PlusIcon className="h-4 w-4 mr-2" />
                Tạo mới
              </Link>
            </Button>
          </div>
        </div>

        <div className="relative">
          <SearchIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Tìm kiếm cuộc trò chuyện..."
            className="pl-9"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
          />
        </div>

        <div className="space-y-4">
          {isLoading ? (
            // Loading skeletons
            Array.from({ length: 3 }).map((_, index) => (
              <Card key={index}>
                <CardContent className="p-4">
                  <div className="flex gap-4">
                    <Skeleton className="h-12 w-12 rounded-full" />
                    <div className="flex-1 space-y-2">
                      <Skeleton className="h-4 w-1/3" />
                      <Skeleton className="h-3 w-2/3" />
                    </div>
                    <Skeleton className="h-3 w-12" />
                  </div>
                </CardContent>
              </Card>
            ))
          ) : filteredConversations.length === 0 ? (
            <div className="text-center p-8">
              <p className="text-muted-foreground mb-4">Không tìm thấy cuộc trò chuyện nào.</p>
              <Button asChild>
                <Link href="/ai">
                  <PlusIcon className="h-4 w-4 mr-2" />
                  Tạo cuộc trò chuyện mới
                </Link>
              </Button>
            </div>
          ) : (
            filteredConversations.map(conversation => (
              <Link href={`/ai/${conversation.ai_agent_id}/chat?conversationId=${conversation.id}`} key={conversation.id}>
                <Card className="cursor-pointer hover:bg-muted/50 transition-colors">
                  <CardContent className="p-4">
                    <div className="flex gap-3">
                      <Avatar className="h-12 w-12">
                        {conversation.aiAgent?.avatar ? (
                          <AvatarImage 
                            src={conversation.aiAgent.avatar.startsWith('http') 
                              ? conversation.aiAgent.avatar 
                              : `${process.env.NEXT_PUBLIC_API_URL?.replace('/api', '')}/storage/${conversation.aiAgent.avatar}`}
                            alt={conversation.aiAgent?.name || 'AI Agent'}
                          />
                        ) : (
                          <AvatarFallback>{(conversation.aiAgent?.name || 'AI').substring(0, 2).toUpperCase()}</AvatarFallback>
                        )}
                      </Avatar>
                      
                      <div className="flex-1 min-w-0">
                        <div className="flex justify-between items-start">
                          <h3 className="font-medium truncate">{conversation.title || 'Untitled Conversation'}</h3>
                          <span className="text-xs text-muted-foreground shrink-0 ml-2">
                            {formatDateTime(conversation.last_message_at)}
                          </span>
                        </div>
                        
                        <div className="flex items-center gap-1 mt-1">
                          <span className="text-xs text-muted-foreground">{conversation.aiAgent?.name || 'Unknown Agent'}</span>
                        </div>
                        
                        {conversation.last_message && (
                          <p className="text-sm text-muted-foreground truncate mt-1">
                            {conversation.last_message.role === 'user' ? 'Bạn: ' : ''}
                            {conversation.last_message.content}
                          </p>
                        )}
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </Link>
            ))
          )}
        </div>
      </div>
    </AppShell>
  )
} 