"use client"

import { AppShell } from "@/components/layout/app-shell"

export default function OrdersPage() {
  return (
    <AppShell>
      <div className="p-8">
        <h1 className="text-3xl font-bold mb-6">Đơn hàng</h1>
        
        <div className="bg-white rounded-lg shadow p-6">
          <h2 className="text-xl font-semibold mb-4">Danh sách đơn hàng</h2>
          
          <div className="overflow-x-auto">
            <table className="min-w-full border-collapse">
              <thead>
                <tr className="bg-gray-50">
                  <th className="py-3 px-4 text-left border-b">Mã đơn</th>
                  <th className="py-3 px-4 text-left border-b">Khách hàng</th>
                  <th className="py-3 px-4 text-left border-b">Ngày đặt</th>
                  <th className="py-3 px-4 text-left border-b">Tổng tiền</th>
                  <th className="py-3 px-4 text-left border-b">Trạng thái</th>
                  <th className="py-3 px-4 text-left border-b">Hành động</th>
                </tr>
              </thead>
              <tbody>
                <tr className="hover:bg-gray-50">
                  <td className="py-3 px-4 border-b">#ORD001</td>
                  <td className="py-3 px-4 border-b">Nguyễn Văn A</td>
                  <td className="py-3 px-4 border-b">26/04/2025</td>
                  <td className="py-3 px-4 border-b">300,000 VNĐ</td>
                  <td className="py-3 px-4 border-b">
                    <span className="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Hoàn thành</span>
                  </td>
                  <td className="py-3 px-4 border-b">
                    <button className="text-blue-500 hover:text-blue-700 mr-2">Xem</button>
                    <button className="text-red-500 hover:text-red-700">Hủy</button>
                  </td>
                </tr>
                <tr className="hover:bg-gray-50">
                  <td className="py-3 px-4 border-b">#ORD002</td>
                  <td className="py-3 px-4 border-b">Trần Thị B</td>
                  <td className="py-3 px-4 border-b">25/04/2025</td>
                  <td className="py-3 px-4 border-b">450,000 VNĐ</td>
                  <td className="py-3 px-4 border-b">
                    <span className="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs">Đang xử lý</span>
                  </td>
                  <td className="py-3 px-4 border-b">
                    <button className="text-blue-500 hover:text-blue-700 mr-2">Xem</button>
                    <button className="text-red-500 hover:text-red-700">Hủy</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </AppShell>
  )
} 