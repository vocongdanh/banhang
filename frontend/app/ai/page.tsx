"use client"

import { useState, useEffect } from "react"
import { AppShell } from "@/components/layout/app-shell"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card"
import { PlusCircleIcon, MessageCircleIcon, SettingsIcon } from "lucide-react"
import { useToast } from "@/components/ui/use-toast"
import Link from "next/link"
import Image from "next/image"
import { getBusinessId } from "@/lib/api-client"

interface AIAgent {
  id: string
  name: string
  avatar: string | null
  description: string | null
  status: string
  capabilities: string[]
}

export default function AIAgentPage() {
  const [agents, setAgents] = useState<AIAgent[]>([])
  const [loading, setLoading] = useState(true)
  const [businessId, setBusinessId] = useState<string | null>(null)
  const [isLoggedIn, setIsLoggedIn] = useState(false)
  const { toast } = useToast()

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
    // Fetch AI agents when business ID is available
    if (businessId && isLoggedIn) {
      fetchAIAgents()
    }
  }, [businessId, isLoggedIn])

  const fetchAIAgents = async () => {
    setLoading(true)
    try {
      const token = localStorage.getItem('auth_token')
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8001/api'
      
      const response = await fetch(`${apiUrl}/ai-agents?business_id=${businessId}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      })
      
      if (!response.ok) {
        throw new Error('Failed to fetch AI agents')
      }
      
      const data = await response.json()
      setAgents(data.data || [])
    } catch (error) {
      console.error('Error fetching AI agents:', error)
      toast({
        title: "Error",
        description: "Failed to load AI agents. Please try again.",
        variant: "destructive"
      })
    } finally {
      setLoading(false)
    }
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
            <p>Vui lòng chọn một doanh nghiệp để xem AI Agents.</p>
          </div>
        </div>
      </AppShell>
    )
  }

  return (
    <AppShell>
      <div className="container mx-auto py-6 space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold">AI Agents</h1>
            <p className="text-muted-foreground">Quản lý và tương tác với các AI Agent cho doanh nghiệp của bạn</p>
          </div>
          <Button asChild>
            <Link href="/ai/create">
              <PlusCircleIcon className="mr-2 h-4 w-4" />
              Tạo Agent mới
            </Link>
          </Button>
        </div>

        {loading ? (
          <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            {[1, 2, 3].map(i => (
              <Card key={i} className="animate-pulse">
                <CardHeader className="h-32 bg-gray-100"></CardHeader>
                <CardContent className="py-4">
                  <div className="h-4 bg-gray-100 rounded w-3/4 mb-3"></div>
                  <div className="h-3 bg-gray-100 rounded w-full mb-2"></div>
                  <div className="h-3 bg-gray-100 rounded w-5/6"></div>
                </CardContent>
                <CardFooter className="flex justify-between py-4">
                  <div className="h-9 bg-gray-100 rounded w-20"></div>
                  <div className="h-9 bg-gray-100 rounded w-20"></div>
                </CardFooter>
              </Card>
            ))}
          </div>
        ) : agents.length === 0 ? (
          <div className="text-center py-12 border border-dashed rounded-lg">
            <h3 className="text-lg font-medium mb-2">Chưa có AI Agent nào</h3>
            <p className="text-muted-foreground mb-6">Tạo AI Agent đầu tiên cho doanh nghiệp của bạn</p>
            <Button asChild>
              <Link href="/ai/create">
                <PlusCircleIcon className="mr-2 h-4 w-4" />
                Tạo Agent mới
              </Link>
            </Button>
          </div>
        ) : (
          <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            {agents.map(agent => (
              <Card key={agent.id}>
                <CardHeader className="flex flex-row items-center gap-4">
                  <div className="bg-primary/10 p-2 rounded-full overflow-hidden w-12 h-12 flex items-center justify-center">
                    {agent.avatar ? (
                      <Image 
                        src={agent.avatar.startsWith('http') 
                          ? agent.avatar 
                          : `${process.env.NEXT_PUBLIC_API_URL?.replace('/api', '')}/storage/${agent.avatar}`}
                        alt={agent.name}
                        width={40}
                        height={40}
                        className="rounded-full object-cover"
                      />
                    ) : (
                      <MessageCircleIcon className="h-6 w-6 text-primary" />
                    )}
                  </div>
                  <div>
                    <CardTitle className="text-lg">{agent.name}</CardTitle>
                    <CardDescription>
                      {agent.status === 'active' ? 
                        <span className="text-green-600">● Active</span> : 
                        <span className="text-gray-400">● Inactive</span>}
                    </CardDescription>
                  </div>
                </CardHeader>
                <CardContent>
                  <p className="text-sm text-muted-foreground line-clamp-2">
                    {agent.description || 'No description provided.'}
                  </p>
                  <div className="mt-3 flex flex-wrap gap-1">
                    {agent.capabilities?.includes('voice') && (
                      <span className="text-xs bg-blue-100 text-blue-800 rounded-full px-2 py-0.5">Voice</span>
                    )}
                    {agent.capabilities?.includes('image_upload') && (
                      <span className="text-xs bg-purple-100 text-purple-800 rounded-full px-2 py-0.5">Images</span>
                    )}
                  </div>
                </CardContent>
                <CardFooter className="flex justify-between">
                  <Button variant="outline" asChild>
                    <Link href={`/ai/${agent.id}/settings`}>
                      <SettingsIcon className="h-4 w-4 mr-2" />
                      Settings
                    </Link>
                  </Button>
                  <Button variant="default" asChild>
                    <Link href={`/ai/${agent.id}/chat`}>
                      <MessageCircleIcon className="h-4 w-4 mr-2" />
                      Chat
                    </Link>
                  </Button>
                </CardFooter>
              </Card>
            ))}
          </div>
        )}
      </div>
    </AppShell>
  )
} 