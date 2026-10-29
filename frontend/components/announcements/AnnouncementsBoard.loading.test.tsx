// @vitest-environment jsdom
import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { fireEvent, screen } from "@testing-library/dom";
import { expect, test, vi } from "vitest";
import { AnnouncementsBoard } from "./AnnouncementsBoard";
import { fetchAnnouncements, updateAnnouncement, type Announcement } from "@/services/announcementService";
vi.mock("@/services/announcementService");
vi.mock("next/navigation", () => ({ useSearchParams: () => new URLSearchParams() }));
vi.mock("@/contexts/AuthContext", () => ({ useAuth: () => ({user:{role:"RND"}}) }));
vi.mock("./SopBanner", () => ({ SopBanner: () => null }));
vi.mock("./AnnouncementAuthorAvatar", () => ({ AnnouncementAuthorAvatar: () => null }));

test("hydrates compact attachments only when editing and omits them from a text-only save", async () => {
  Object.assign(globalThis, {IS_REACT_ACT_ENVIRONMENT:true});
  const post = {id:"fictional",title:"Demo post",body:"Demo body",category:"General",visibility:"All",pinned:false,can_edit:true,can_delete:true,author:{name:"Fictional author",role:"RND"},created_at:"2026-10-29",attachments:["/api/announcements/fictional/attachments/object/file"]} as Announcement;
  const meta={current_page:1,last_page:1,total:1,per_page:10};
  let finish!: (value: {data:Announcement[];meta:typeof meta}) => void;
  vi.mocked(fetchAnnouncements).mockImplementation((_p,_n,_id,compact) => compact === false
    ? new Promise(resolve => {finish=resolve;}) : Promise.resolve({data:[post],meta}));
  vi.mocked(updateAnnouncement).mockResolvedValue(post);
  const container=document.createElement("div");document.body.append(container);const root=createRoot(container);
  try {
    await act(async()=>root.render(<AnnouncementsBoard variant="rnd"/>));
    await act(async()=>fireEvent.click(screen.getByRole("button",{name:"Edit"})));
    expect(screen.getByRole("status").textContent).toContain("Loading announcement for editing");
    await act(async()=>finish({data:[{...post,attachments:["data:image/png;base64,ZmFrZQ=="]}],meta}));
    await act(async()=>fireEvent.click(screen.getByRole("button",{name:"Save Changes"})));
    expect(updateAnnouncement).toHaveBeenCalledWith("fictional", expect.not.objectContaining({attachments:expect.anything()}));
  } finally {act(()=>root.unmount());container.remove();vi.resetAllMocks();}
});
